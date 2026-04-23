<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Facture #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; line-height: 1.5; }
        .container { padding: 40px; }
        .header { display: table; width: 100%; margin-bottom: 40px; }
        .header-left { display: table-cell; width: 55%; vertical-align: top; }
        .header-right { display: table-cell; width: 45%; vertical-align: top; text-align: right; }
        .company-name { font-size: 20px; font-weight: bold; color: #111; margin-bottom: 2px; }
        .company-legal { font-size: 10px; color: #777; margin-bottom: 4px; }
        .company-details { color: #555; font-size: 11px; }
        .invoice-title { font-size: 28px; font-weight: bold; color: #111; margin-bottom: 4px; }
        .invoice-meta { color: #555; font-size: 11px; }
        .divider { border-top: 2px solid #e5e5e5; margin: 24px 0; }
        .addresses { display: table; width: 100%; margin-bottom: 30px; }
        .address-block { display: table-cell; width: 50%; vertical-align: top; }
        .address-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 6px; font-weight: bold; }
        .address-value { font-size: 12px; color: #333; }
        .address-value .tax-id { font-size: 10px; color: #666; margin-top: 4px; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        table.items th { background: #f5f5f5; padding: 10px 12px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #555; border-bottom: 2px solid #ddd; }
        table.items td { padding: 12px; border-bottom: 1px solid #eee; }
        table.items .text-right { text-align: right; }
        .totals { width: 320px; margin-left: auto; }
        .totals table { width: 100%; }
        .totals td { padding: 5px 0; }
        .totals .subtotal-row td { color: #555; }
        .totals .tax-row td { color: #555; border-bottom: 1px solid #ddd; padding-bottom: 8px; }
        .totals .total-row td { border-top: 2px solid #111; font-weight: bold; font-size: 14px; padding-top: 10px; }
        .totals .text-right { text-align: right; }
        .legal-notice { margin-top: 24px; font-size: 10px; color: #888; border: 1px solid #eee; padding: 10px 14px; border-radius: 4px; }
        .footer { margin-top: 30px; text-align: center; color: #888; font-size: 10px; border-top: 1px solid #eee; padding-top: 20px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; vertical-align: middle; position: relative; top: -1px; }
        .invoice-status { margin-top: 4px; }
        .badge-paid { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef9c3; color: #854d0e; }
    </style>
</head>
<body>
<div class="container">

    {{-- ===== HEADER: seller info + invoice number ===== --}}
    <div class="header">
        <div class="header-left">
            @if(!empty($company['name']))
                <div class="company-name">{{ $company['name'] }}@if(!empty($company['legal_form'])) <span style="font-weight:normal;font-size:14px;">{{ $company['legal_form'] }}</span>@endif</div>
            @endif
            <div class="company-details">
                @if(!empty($company['address'])){{ $company['address'] }}<br>@endif
                @if(!empty($company['zip']) || !empty($company['city']))
                    {{ $company['zip'] }} {{ $company['city'] }}<br>
                @endif
                @if(!empty($company['country'])){{ $company['country'] }}<br>@endif
                @if(!empty($company['phone']))Tél : {{ $company['phone'] }}<br>@endif
                @if(!empty($company['email'])){{ $company['email'] }}<br>@endif
                @if(!empty($company['vat']))N° TVA : {{ $company['vat'] }}<br>@endif
                @if(!empty($company['siret']))SIRET : {{ $company['siret'] }}@endif
            </div>
        </div>
        <div class="header-right">
            <div class="invoice-title">FACTURE</div>
            <div class="invoice-meta">
                <strong>N° {{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</strong><br>
                Date d'émission : {{ $order->created_at->format('d/m/Y') }}<br>
                Date d'échéance : {{ $order->created_at->format('d/m/Y') }}<br>
                <span class="invoice-status">Statut : <span class="badge {{ $order->status->value === 'active' ? 'badge-paid' : 'badge-pending' }}">
                    {{ $order->status->value === 'active' ? 'PAYÉE' : strtoupper($order->status->getLabel()) }}
                </span></span>
            </div>
        </div>
    </div>

    <div class="divider"></div>

    {{-- ===== ADDRESSES: seller recap + buyer ===== --}}
    <div class="addresses">
        <div class="address-block">
            <div class="address-label">Facturer à</div>
            <div class="address-value">
                @if(!empty($order->customer->company_name))
                    <strong>{{ $order->customer->company_name }}</strong><br>
                @endif
                <strong>{{ $order->customer->first_name }} {{ $order->customer->last_name }}</strong><br>
                {{ $order->customer->user->email }}
                @if(!empty($order->customer->address))
                    <br>{{ $order->customer->address }}
                    @if(!empty($order->customer->address2))<br>{{ $order->customer->address2 }}@endif
                @endif
                @if(!empty($order->customer->zip) || !empty($order->customer->city))
                    <br>{{ $order->customer->zip }} {{ $order->customer->city }}
                @endif
                @if(!empty($order->customer->country))
                    <br>{{ $order->customer->country }}
                @endif
                @if(!empty($order->customer->vat_number) || !empty($order->customer->siret))
                    <div class="tax-id">
                        @if(!empty($order->customer->vat_number))N° TVA : {{ $order->customer->vat_number }}<br>@endif
                        @if(!empty($order->customer->siret))SIRET : {{ $order->customer->siret }}@endif
                    </div>
                @endif
            </div>
        </div>
        <div class="address-block">
            <div class="address-label">Détails du paiement</div>
            <div class="address-value">
                @if($order->payment_gateway)
                    Moyen de paiement : {{ ucfirst($order->payment_gateway) }}<br>
                @endif
                Référence : INV-{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}<br>
                @if($order->expires_at)
                    Service valide jusqu'au : {{ $order->expires_at->format('d/m/Y') }}
                @endif
            </div>
        </div>
    </div>

    {{-- ===== LINE ITEMS ===== --}}
    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th>Période</th>
                @if($tax['enabled'])
                    <th class="text-right">P.U. HT</th>
                @else
                    <th class="text-right">Montant</th>
                @endif
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $order->packPrice->pack->name }}</strong><br>
                    <span style="color: #666; font-size: 11px;">{{ $order->packPrice->name }}</span>
                </td>
                <td>
                    {{ $order->packPrice->interval_value }}
                    {{ str_plural($order->packPrice->interval_type->getLabel(), $order->packPrice->interval_value) }}
                </td>
                <td class="text-right">
                    @if($tax['enabled'])
                        {{ $order->packPrice->formatCostRaw($tax['amount_ht']) }}
                    @else
                        {{ $order->packPrice->formatCost() }}
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    {{-- ===== TOTALS ===== --}}
    <div class="totals">
        <table>
            @if($tax['enabled'])
                <tr class="subtotal-row">
                    <td>Sous-total HT</td>
                    <td class="text-right">{{ $order->packPrice->formatCostRaw($tax['amount_ht']) }}</td>
                </tr>
                <tr class="tax-row">
                    <td>{{ $tax['label'] }} ({{ $tax['rate_percent'] }})</td>
                    <td class="text-right">{{ $order->packPrice->formatCostRaw($tax['amount_tax']) }}</td>
                </tr>
                <tr class="total-row">
                    <td>Total TTC</td>
                    <td class="text-right">{{ $order->packPrice->formatCostRaw($tax['amount_ttc']) }}</td>
                </tr>
            @else
                <tr class="subtotal-row">
                    <td>Sous-total</td>
                    <td class="text-right">{{ $order->packPrice->formatCost() }}</td>
                </tr>
                <tr class="total-row">
                    <td>Total</td>
                    <td class="text-right">{{ $order->packPrice->formatCost() }}</td>
                </tr>
            @endif
        </table>
    </div>

    {{-- ===== LEGAL NOTICES ===== --}}
    <div class="legal-notice">
        @if(!$tax['enabled'])
            TVA non applicable – article 293 B du CGI.<br>
        @endif
        Tout retard de paiement entraîne des pénalités de retard au taux légal en vigueur,
        ainsi qu'une indemnité forfaitaire de recouvrement de 40 € (art. L.441-10 du Code de commerce).
    </div>

    {{-- ===== FOOTER ===== --}}
    <div class="footer">
        @if(!empty($company['name']))
            {{ $company['name'] }}
            @if(!empty($company['legal_form'])) – {{ $company['legal_form'] }} @endif
            @if(!empty($company['siret'])) – SIRET {{ $company['siret'] }} @endif
            @if(!empty($company['website'])) &middot; {{ $company['website'] }} @endif
            @if(!empty($company['email'])) &middot; {{ $company['email'] }} @endif
        @endif
        <br>
        Cette facture a été générée automatiquement. Merci de votre confiance.
    </div>

</div>
</body>
</html>
