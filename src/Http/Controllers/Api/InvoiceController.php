<?php

namespace Fywolf\Billing\Http\Controllers\Api;

use Fywolf\Billing\Models\Customer;
use Fywolf\Billing\Models\Order;
use Fywolf\Billing\Services\InvoiceService;
use Illuminate\Http\Response;

class InvoiceController
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function download(Order $order): Response
    {
        $customer = Customer::where('user_id', auth()->id())->first();

        if (!$customer || $order->customer_id !== $customer->id) {
            abort(403);
        }

        return $this->streamPdf($order);
    }

    public function adminDownload(Order $order): Response
    {
        if (!auth()->user()->isAdmin()) {
            abort(403);
        }

        return $this->streamPdf($order);
    }

    private function streamPdf(Order $order): Response
    {
        $pdf = $this->invoiceService->generatePdf($order);

        if ($pdf === null) {
            abort(503, 'PDF generation requires the barryvdh/laravel-dompdf package.');
        }

        $filename = 'invoice-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT) . '.pdf';

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
