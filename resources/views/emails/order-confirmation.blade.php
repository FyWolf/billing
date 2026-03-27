<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f4f4f5; margin: 0; padding: 0; color: #1a1a1a; }
        .wrapper { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
        .card { background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .card-header { background: #111827; padding: 24px 32px; text-align: center; }
        .card-header h1 { color: #ffffff; font-size: 20px; margin: 0; }
        .card-body { padding: 32px; }
        .success-icon { text-align: center; margin-bottom: 20px; font-size: 48px; }
        h2 { font-size: 22px; margin: 0 0 8px; text-align: center; }
        .subtitle { color: #6b7280; text-align: center; margin-bottom: 28px; font-size: 14px; }
        .details { background: #f9fafb; border-radius: 6px; padding: 20px; margin-bottom: 24px; }
        .details table { width: 100%; border-collapse: collapse; }
        .details td { padding: 8px 0; font-size: 14px; }
        .details td:first-child { color: #6b7280; }
        .details td:last-child { text-align: right; font-weight: 600; }
        .details tr:not(:last-child) td { border-bottom: 1px solid #e5e7eb; }
        .total-row td { font-size: 16px !important; color: #111 !important; }
        .cta { text-align: center; margin: 24px 0; }
        .cta a { display: inline-block; background: #111827; color: #ffffff; text-decoration: none; padding: 12px 32px; border-radius: 6px; font-weight: 600; font-size: 14px; }
        .footer { text-align: center; padding: 20px 32px; color: #9ca3af; font-size: 12px; }
        .note { background: #eff6ff; border-left: 3px solid #3b82f6; padding: 12px 16px; margin-bottom: 24px; border-radius: 0 6px 6px 0; font-size: 13px; color: #1e40af; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="card-header">
            <h1>{{ config('app.name', 'Panel') }}</h1>
        </div>
        <div class="card-body">
            <div class="success-icon">&#10003;</div>
            <h2>Order Confirmed!</h2>
            <p class="subtitle">
                Thank you for your purchase, {{ $order->customer->first_name }}.
                Your order has been received and is being processed.
            </p>

            <div class="note">
                A PDF invoice is attached to this email for your records.
            </div>

            <div class="details">
                <table>
                    <tr>
                        <td>Order Number</td>
                        <td>#{{ $order->id }}</td>
                    </tr>
                    <tr>
                        <td>Product</td>
                        <td>{{ $order->productPrice->product->name }}</td>
                    </tr>
                    <tr>
                        <td>Plan</td>
                        <td>{{ $order->productPrice->name }}</td>
                    </tr>
                    <tr>
                        <td>Billing Period</td>
                        <td>
                            {{ $order->productPrice->interval_value }}
                            {{ str_plural($order->productPrice->interval_type->getLabel(), $order->productPrice->interval_value) }}
                        </td>
                    </tr>
                    @if($order->payment_gateway)
                    <tr>
                        <td>Payment Method</td>
                        <td>{{ ucfirst($order->payment_gateway) }}</td>
                    </tr>
                    @endif
                    @if($order->expires_at)
                    <tr>
                        <td>Valid Until</td>
                        <td>{{ $order->expires_at->format('M d, Y') }}</td>
                    </tr>
                    @endif
                    <tr class="total-row">
                        <td>Total</td>
                        <td>{{ $order->productPrice->formatCost() }}</td>
                    </tr>
                </table>
            </div>

            <div class="cta">
                <a href="{{ url('/') }}">Go to Dashboard</a>
            </div>
        </div>
        <div class="footer">
            @if(!empty($company['name']))
                {{ $company['name'] }}
                @if(!empty($company['website'])) &middot; {{ $company['website'] }} @endif
                <br>
            @endif
            This is an automated message. Please do not reply to this email.
        </div>
    </div>
</div>
</body>
</html>
