<?php

namespace App\Services;

use App\Models\Cart;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class CartService
{
    /*
    |--------------------------------------------------------------------------
    | CART IDENTIFICATION
    |--------------------------------------------------------------------------
    */

    public function getOrCreateCartId(?int $userId, ?string $tempUserId): string
    {
        $existing = Cart::active()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(!$userId && $tempUserId, fn ($q) => $q->where('temporary_user_id', $tempUserId))
            ->first();

        return $existing?->cart_id ?? (string) Str::uuid();
    }

    /*
    |--------------------------------------------------------------------------
    | ADD ITEM
    |--------------------------------------------------------------------------
    */

    public function addItem(
        int $estateId,
        int $plotId,
        float $price,
        float $amount,
        ?int $userId = null,
        ?string $tempUserId = null
    ): Cart {

        $cartId = $this->getOrCreateCartId($userId, $tempUserId);

        // prevent duplicate plot inside same cart
        $exists = Cart::active()
            ->where('cart_id', $cartId)
            ->where('plot_id', $plotId)
            ->exists();

        if ($exists) {
            throw new \Exception("Plot already in cart");
        }

        return Cart::create([
            'cart_id' => $cartId,
            'estate_id' => $estateId,
            'plot_id' => $plotId,
            'price' => $price,
            'amount' => $amount,
            'user_id' => $userId,
            'temporary_user_id' => $tempUserId,
            'cart_status' => 'active',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE ITEM
    |--------------------------------------------------------------------------
    */

    public function removeItem(int $cartItemId): void
    {
        Cart::where('id', $cartItemId)->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | GET CART ITEMS
    |--------------------------------------------------------------------------
    */

    public function getCartItems(?int $userId = null, ?string $tempUserId = null): Collection
    {
        return Cart::active()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(!$userId && $tempUserId, fn ($q) => $q->where('temporary_user_id', $tempUserId))
            ->with(['estate', 'plot'])
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | CART TOTAL
    |--------------------------------------------------------------------------
    */

    public function getCartTotal(?int $userId = null, ?string $tempUserId = null): float
    {
        return $this->getCartItems($userId, $tempUserId)
            ->sum('amount'); //AMOUNT IN Cart customer want to pay
    }

     public function getCartTotalPrice(?int $userId = null, ?string $tempUserId = null): float
    {
        return $this->getCartItems($userId, $tempUserId)
            ->sum('price'); //TOTAL PRICE IN Cart customer want to pay
    }


    /*
    |--------------------------------------------------------------------------
    | CLEAR CART
    |--------------------------------------------------------------------------
    */

    public function clearCart(?int $userId = null, ?string $tempUserId = null): void
    {
        Cart::active()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(!$userId && $tempUserId, fn ($q) => $q->where('temporary_user_id', $tempUserId))
            ->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | MERGE GUEST CART AFTER LOGIN
    |--------------------------------------------------------------------------
    */

    public function mergeGuestCart(string $tempUserId, int $userId): void
    {
        $guestItems = Cart::active()
            ->where('temporary_user_id', $tempUserId)
            ->get();

        if ($guestItems->isEmpty()) {
            return;
        }

        $userCartId = $this->getOrCreateCartId($userId, null);

        foreach ($guestItems as $item) {

            // skip duplicates
            $exists = Cart::active()
                ->where('cart_id', $userCartId)
                ->where('plot_id', $item->plot_id)
                ->exists();

            if (!$exists) {
                $item->update([
                    'user_id' => $userId,
                    'temporary_user_id' => null,
                    'cart_id' => $userCartId,
                ]);
            } else {
                $item->delete();
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CHECKOUT PREP
    |--------------------------------------------------------------------------
    */

    public function markCartAsCheckedOut(int $userId, string $invoiceNumber): void
    {
        Cart::where('user_id', $userId)
            ->where('cart_status', 'active')
            ->update(['cart_status' => 'checked_out', 'cart_id' => $invoiceNumber]);
    }

    public function getCartIDByUser(int $userId): ?string
    {
        return Cart::active()
            ->where('user_id', $userId)
            ->value('cart_id');
    }
}
