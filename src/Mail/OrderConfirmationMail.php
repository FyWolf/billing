<?php

namespace Fywolf\Billing\Mail;

use Fywolf\Billing\Models\Order;
use Fywolf\Billing\Services\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
    ) {}

    public function envelope(): Envelope
    {
        $appName = config('app.name', 'Panel');

        return new Envelope(
            subject: "$appName - Order #{$this->order->id} Confirmation",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'billing::emails.order-confirmation',
            with: [
                'order'   => $this->order,
                'company' => config('billing.company'),
            ],
        );
    }

    public function attachments(): array
    {
        $pdf = app(InvoiceService::class)->generatePdf($this->order);

        if ($pdf === null) {
            return [];
        }

        $invoiceNumber = str_pad($this->order->id, 6, '0', STR_PAD_LEFT);

        return [
            Attachment::fromData(fn () => $pdf, "invoice-{$invoiceNumber}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
