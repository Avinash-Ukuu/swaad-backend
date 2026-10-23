<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $status,
    ) {}

    public function envelope(): Envelope
    {
        $subjects = [
            'confirmed' => 'Your SWAAD order is confirmed - ' . $this->order->order_number,
            'shipped' => 'Your SWAAD order is on its way - ' . $this->order->order_number,
            'delivered' => 'Your SWAAD order has been delivered - ' . $this->order->order_number,
        ];

        return new Envelope(subject: $subjects[$this->status] ?? 'SWAAD order update');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.orders.order-' . $this->status);
    }

    public function attachments(): array
    {
        return [];
    }
}
