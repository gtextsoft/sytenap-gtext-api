<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Str;

class InvoiceService
{
    /**
     * Generate a new invoice for a user
     */
    public function createInvoice(int $userId, float $amount, float $totalPrice, string $paymentStatus = 'pending', int $agentId = null): Invoice
    {
        $invoiceNumber = $this->generateInvoiceNumber();
        $outstandingAmount = $totalPrice - $amount; // Assuming amount is the paid amount and price is the total price
        $outstanding_payment_status = $outstandingAmount > 0 ? 'unpaid' : 'paid';

        return Invoice::create([
            'user_id' => $userId,
            'invoice_number' => $invoiceNumber,
            'amount' => $amount,
            'outstanding_amount' => $outstandingAmount,
            'outstanding_payment_status' => $outstanding_payment_status,
            'payment_status' => $paymentStatus,
            'agent_id' => $agentId,
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
