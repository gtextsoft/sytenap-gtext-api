<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Str;

class InvoiceService
{
    /**
     * Generate a new invoice for a user
     */
    public function createInvoice(int $userId, float $amount, string $invoiceNumber, string $paymentStatus = 'pending'): Invoice
    {
        return Invoice::create([
            'user_id' => $userId,
            'invoice_number' => $invoiceNumber,
            'amount' => $amount,
            'payment_status' => $paymentStatus,
        ]);
    }

    /**
     * Generate a unique invoice number
     */
    private function generateInvoiceNumber(): string
    {
        // Example: INV-20260212-5F3A2B
        return 'INV-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus(Invoice $invoice, string $status): Invoice
    {
        $invoice->update([
            'payment_status' => $status,
        ]);

        return $invoice;
    }
}
