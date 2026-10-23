<?php

namespace App\Services;

use App\Mail\OrderStatusMail;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OrderStatusEmailService
{
    /**
     * Send the email for a lifecycle milestone at most once under normal
     * concurrent request conditions. Email failures are logged and do not
     * roll back payment/order state.
     */
    public function sendIfNeeded(Order $order, string $status): void
    {
        $fields = [
            'confirmed' => 'confirmed_email_sent_at',
            'shipped' => 'shipped_email_sent_at',
            'delivered' => 'delivered_email_sent_at',
        ];

        if (!isset($fields[$status])) {
            return;
        }

        $order->refresh();

        if ($status === 'confirmed' && ($order->payment_status !== 'paid' || !in_array($order->order_status, ['confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered'], true))) {
            return;
        }

        if ($status === 'shipped' && $order->order_status !== 'shipped') {
            return;
        }

        if ($status === 'delivered' && $order->order_status !== 'delivered') {
            return;
        }

        if (!$order->customer_email) {
            Log::info('Order status email skipped because the order has no customer email.', [
                'order_id' => $order->id,
                'status' => $status,
            ]);
            return;
        }

        $field = $fields[$status];
        $lock = Cache::lock("swaad-order-email:{$order->id}:{$status}", 60);

        try {
            $lock->block(5, function () use ($order, $field, $status) {
                $fresh = Order::with('items')->find($order->id);

                if (!$fresh || $fresh->{$field}) {
                    return;
                }

                Mail::to($fresh->customer_email)->send(new OrderStatusMail($fresh, $status));

                $fresh->forceFill([$field => now()])->save();
            });
        } catch (Throwable $e) {
            // Never let an email provider failure undo a paid order or admin status update.
            Log::error('Failed to send SWAAD order status email.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $status,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
