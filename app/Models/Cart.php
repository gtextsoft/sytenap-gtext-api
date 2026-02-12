<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Cart extends Model
{
    protected $fillable = [
        'cart_id',
        'estate_id',
        'plot_id',
        'price',
        'user_id',
        'temporary_user_id',
        'cart_status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function estate()
    {
        return $this->belongsTo(Estate::class);
    }

    public function plot()
    {
        return $this->belongsTo(Plot::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    // Active cart items
    public function scopeActive(Builder $query)
    {
        return $query->where('cart_status', 'active');
    }

    // Get items by cart group
    public function scopeByCartId(Builder $query, string $cartId)
    {
        return $query->where('cart_id', $cartId);
    }

    // Logged-in user cart
    public function scopeForUser(Builder $query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Guest cart
    public function scopeForGuest(Builder $query, string $tempId)
    {
        return $query->where('temporary_user_id', $tempId);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    // Check if item belongs to guest
    public function isGuest(): bool
    {
        return is_null($this->user_id) && !is_null($this->temporary_user_id);
    }
}
