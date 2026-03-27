<?php

namespace Fywolf\Billing\Services;

use Fywolf\Billing\Models\Order;

class InvoiceService
{
    public function generatePdf(Order $order): ?string
    {
        if (!class_exists(\Barryvdh\DomPDF\ServiceProvider::class)) {
            return null;
        }

        $order->load(['productPrice.product', 'customer.user']);

        $data = [
            'order'   => $order,
            'company' => config('billing.company'),
            'currency' => config('billing.currency', 'USD'),
        ];

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = app('dompdf.wrapper');
        $pdf->loadView('billing::invoice', $data);
        $pdf->setPaper('a4');

        return $pdf->output();
    }
}
