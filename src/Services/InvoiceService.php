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

        $order->load(['productPrice.product', 'customer.user', 'coupon']);

        $tax = $this->computeTax($order->productPrice->cost);

        $data = [
            'order'    => $order,
            'company'  => config('billing.company'),
            'currency' => config('billing.currency', 'USD'),
            'tax'      => $tax,
        ];

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = app('dompdf.wrapper');
        $pdf->loadView('billing::invoice', $data);
        $pdf->setPaper('a4');

        return $pdf->output();
    }

    /**
     * Compute tax breakdown for a given cost.
     *
     * Returns an array with:
     *   enabled       — whether tax display is active
     *   label         — e.g. "TVA"
     *   rate          — e.g. 0.20
     *   rate_percent  — e.g. "20 %"
     *   amount_ht     — price excluding tax
     *   amount_tax    — tax amount
     *   amount_ttc    — price including tax
     */
    public function computeTax(float $cost): array
    {
        $enabled           = (bool) config('billing.tax.enabled', false);
        $rate              = (float) config('billing.tax.rate', 0.20);
        $pricesIncludeTax  = (bool) config('billing.tax.prices_include_tax', false);
        $label             = config('billing.tax.label', 'TVA');

        if (!$enabled) {
            return [
                'enabled'      => false,
                'label'        => $label,
                'rate'         => $rate,
                'rate_percent' => number_format($rate * 100, 0) . ' %',
                'amount_ht'    => $cost,
                'amount_tax'   => 0.0,
                'amount_ttc'   => $cost,
            ];
        }

        if ($pricesIncludeTax) {
            $amountTtc = $cost;
            $amountHt  = round($cost / (1 + $rate), 2);
            $amountTax = round($amountTtc - $amountHt, 2);
        } else {
            $amountHt  = $cost;
            $amountTax = round($cost * $rate, 2);
            $amountTtc = round($amountHt + $amountTax, 2);
        }

        return [
            'enabled'      => true,
            'label'        => $label,
            'rate'         => $rate,
            'rate_percent' => number_format($rate * 100, 0) . ' %',
            'amount_ht'    => $amountHt,
            'amount_tax'   => $amountTax,
            'amount_ttc'   => $amountTtc,
        ];
    }
}
