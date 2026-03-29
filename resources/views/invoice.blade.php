<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $order->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; line-height: 1.5; }
        .container { padding: 40px; }
        .header { display: table; width: 100%; margin-bottom: 40px; }
        .header-left { display: table-cell; width: 50%; vertical-align: top; }
        .header-right { display: table-cell; width: 50%; vertical-align: top; text-align: right; }
        .company-name { font-size: 20px; font-weight: bold; color: #111; margin-bottom: 4px; }
        .company-details { color: #555; font-size: 11px; }
        .invoice-title { font-size: 28px; font-weight: bold; color: #111; margin-bottom: 4px; }
        .invoice-meta { color: #555; font-size: 11px; }
        .divider { border-top: 2px solid #e5e5e5; margin: 24px 0; }
        .addresses { display: table; width: 100%; margin-bottom: 30px; }
        .address-block { display: table-cell; width: 50%; vertical-align: top; }
        .address-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 6px; font-weight: bold; }
        .address-value { font-size: 12px; color: #333; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        table.items th { background: #f5f5f5; padding: 10px 12px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #555; border-bottom: 2px solid #ddd; }
        table.items td { padding: 12px; border-bottom: 1px solid #eee; }
        table.items .text-right { text-align: right; }
        .totals { width: 300px; margin-left: auto; }
        .totals table { width: 100%; }
        .totals td { padding: 6px 0; }
        .totals .total-row td { border-top: 2px solid #111; font-weight: bold; font-size: 14px; padding-top: 10px; }
        .footer { margin-top: 50px; text-align: center; color: #888; font-size: 10px; border-top: 1px solid #eee; padding-top: 20px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-paid { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef9c3; color: #854d0e; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="header-left">
            @if(!empty($company['name']))
                <div class="company-name">{{ $company['name'] }}</div>
            @endif
            <div class="company-details">
                @if(!empty($company['address'])){{ $company['address'] }}<br>@endif
                @if(!empty($company['city']) || !empty($company['zip']))
                    {{ $company['zip'] }} {{ $company['city'] }}<br>
                @endif
                @if(!empty($company['country'])){{ $company['country'] }}<br>@endif
                @if(!empty($company['phone']))Tel: {{ $company['phone'] }}<br>@endif
                @if(!empty($company['email'])){{ $company['email'] }}<br>@endif
                @if(!empty($company['vat']))VAT: {{ $company['vat'] }}@endif
            </div>
        </div>
        <div class="header-right">
            <div class="invoice-title">INVOICE</div>
            <div class="invoice-meta">
                <strong>#{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</strong><br>
                Date: {{ $order->created_at->format('F d, Y') }}<br>
                Status: <span class="badge {{ $order->status->value === 'active' ? 'badge-paid' : 'badge-pending' }}">
                    {{ $order->status->value === 'active' ? 'PAID' : strtoupper($order->status->getLabel()) }}
                </span>
            </div>
        </div>
    </div>

    <div class="divider"></div>

    <div class="addresses">
        <div class="address-block">
            <div class="address-label">Bill To</div>
            <div class="address-value">
                <strong>{{ $order->customer->first_name }} {{ $order->customer->last_name }}</strong><br>
                {{ $order->customer->user->email }}
            </div>
        </div>
        <div class="address-block">
            <div class="address-label">Payment Details</div>
            <div class="address-value">
                @if($order->payment_gateway)
                    Method: {{ ucfirst($order->payment_gateway) }}<br>
                @endif
                Reference: INV-{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}<br>
                @if($order->expires_at)
                    Valid until: {{ $order->expires_at->format('F d, Y') }}
                @endif
            </div>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th>Period</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $order->productPrice->product->name }}</strong><br>
                    <span style="color: #666; font-size: 11px;">{{ $order->productPrice->name }}</span>
                </td>
                <td>
                    {{ $order->productPrice->interval_value }}
                    {{ str_plural($order->productPrice->interval_type->getLabel(), $order->productPrice->interval_value) }}
                </td>
                <td class="text-right">{{ $order->productPrice->formatCost() }}</td>
            </tr>
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td>Subtotal</td>
                <td class="text-right">{{ $order->productPrice->formatCost() }}</td>
            </tr>
            <tr class="total-row">
                <td>Total</td>
                <td class="text-right">{{ $order->productPrice->formatCost() }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        @if(!empty($company['name']))
            {{ $company['name'] }}
            @if(!empty($company['website'])) &middot; {{ $company['website'] }} @endif
            @if(!empty($company['email'])) &middot; {{ $company['email'] }} @endif
        @endif
        <br>
        This invoice was generated automatically. Thank you for your business.
    </div>
</div>
</body>
</html>
