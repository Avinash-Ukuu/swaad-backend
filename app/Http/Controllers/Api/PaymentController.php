<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Order, Payment};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Http};
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function verify(Request $request)
    {
        // Legacy Standard Checkout verification is retained for orders created before
        // the Payment Link migration. New Payment Link checkouts never use this route.
        $d = $request->validate([
            'order_number' => 'required|string',
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $order = Order::where('order_number', $d['order_number'])->with('payments')->firstOrFail();
        $payment = $order->payments()->where('razorpay_order_id', $d['razorpay_order_id'])->firstOrFail();
        if ($payment->razorpay_payment_id && $payment->razorpay_payment_id !== $d['razorpay_payment_id']) {
            return response()->json(['message' => 'Payment ID does not match this attempt.'], 422);
        }

        $secret = config('services.razorpay.key_secret');
        $expected = hash_hmac('sha256', $d['razorpay_order_id'].'|'.$d['razorpay_payment_id'], (string) $secret);
        if (!$secret || !hash_equals($expected, $d['razorpay_signature'])) {
            return response()->json(['message' => 'Invalid payment signature.'], 422);
        }

        $response = Http::withBasicAuth(config('services.razorpay.key_id'), $secret)
            ->timeout(15)->get('https://api.razorpay.com/v1/payments/'.$d['razorpay_payment_id']);
        if (!$response->successful()) {
            return response()->json(['message' => 'Payment status could not be confirmed. Please wait for confirmation.'], 503);
        }
        $remote = $response->json();
        if (($remote['order_id'] ?? null) !== $payment->razorpay_order_id
            || (int) ($remote['amount'] ?? 0) !== (int) round((float) $order->total_amount * 100)
            || ($remote['currency'] ?? null) !== 'INR') {
            return response()->json(['message' => 'Payment details do not match the order.'], 422);
        }

        DB::transaction(function () use ($order, $payment, $d, $remote) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $o = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (($remote['status'] ?? '') === 'captured') {
                $p->update(['razorpay_payment_id' => $d['razorpay_payment_id'], 'status' => 'paid', 'signature_verified_at' => now(), 'paid_at' => now()]);
                $this->markOrderPaid($o);
            } elseif (($remote['status'] ?? '') === 'failed' && $p->status !== 'paid' && $o->payment_status !== 'paid') {
                $p->update(['razorpay_payment_id' => $d['razorpay_payment_id'], 'status' => 'failed', 'signature_verified_at' => now(), 'failure_reason' => Str::limit($remote['error_description'] ?? 'Payment failed', 500)]);
                $o->update(['payment_status' => 'failed']);
            } else {
                if ($p->status !== 'paid') $p->update(['razorpay_payment_id' => $d['razorpay_payment_id'], 'status' => 'pending', 'signature_verified_at' => now()]);
            }
        });

        $order->refresh();
        return response()->json(['success' => true, 'data' => ['order_number' => $order->order_number, 'payment_status' => $order->payment_status, 'order_status' => $order->order_status]]);
    }

    public function callback(Request $request)
    {
        $d = $request->validate([
            'razorpay_payment_id' => 'required|string',
            'razorpay_payment_link_id' => 'required|string',
            'razorpay_payment_link_reference_id' => 'required|string',
            'razorpay_payment_link_status' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $secret = config('services.razorpay.key_secret');
        $message = $d['razorpay_payment_link_id'].'|'.$d['razorpay_payment_link_reference_id'].'|'.$d['razorpay_payment_link_status'].'|'.$d['razorpay_payment_id'];
        $expected = hash_hmac('sha256', $message, (string) $secret);
        if (!$secret || !hash_equals($expected, $d['razorpay_signature'])) {
            return response()->json(['message' => 'Invalid Payment Link callback signature.'], 422);
        }

        $payment = Payment::where('razorpay_payment_link_id', $d['razorpay_payment_link_id'])->with('order')->firstOrFail();
        if ($payment->order->order_number !== $d['razorpay_payment_link_reference_id']) {
            return response()->json(['message' => 'Payment Link reference does not match the order.'], 422);
        }

        $verified = $this->fetchAndApplyPaymentLink($payment, $d['razorpay_payment_id']);
        $frontend = rtrim((string) config('services.razorpay.frontend_url'), '/');
        if ($frontend !== '') {
            return redirect()->away($frontend.'/order-confirmation?order_number='.urlencode($payment->order->order_number).'&payment_check='.($verified ? 'complete' : 'pending'));
        }
        return response()->json(['success' => true, 'verified' => $verified, 'order_number' => $payment->order->order_number]);
    }

    public function webhook(Request $request)
    {
        $secret = config('services.razorpay.webhook_secret');
        $signature = $request->header('X-Razorpay-Signature');
        $rawBody = $request->getContent();
        if (!$secret || !$signature || !hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }

        $event = json_decode($rawBody, true);
        if (!is_array($event)) return response()->json(['message' => 'Invalid webhook payload.'], 400);
        $type = $event['event'] ?? '';
        $eventId = $request->header('X-Razorpay-Event-Id') ?: ($event['id'] ?? null);

        if ($eventId && DB::table('razorpay_webhook_events')->where('event_id', $eventId)->exists()) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        $link = $event['payload']['payment_link']['entity'] ?? null;
        if (is_array($link) && !empty($link['id'])) {
            $payment = Payment::where('razorpay_payment_link_id', $link['id'])->with('order')->first();
            if ($payment) {
                $paymentId = $this->extractPaymentId($link);
                if (in_array($type, ['payment_link.paid', 'payment_link.partially_paid'], true)) {
                    $this->fetchAndApplyPaymentLink($payment, $paymentId);
                } elseif (in_array($type, ['payment_link.expired', 'payment_link.cancelled'], true)) {
                    DB::transaction(function () use ($payment, $type) {
                        $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
                        $o = Order::whereKey($p->order_id)->lockForUpdate()->firstOrFail();
                        if ($p->status !== 'paid' && $o->payment_status !== 'paid') {
                            $p->update(['status' => $type === 'payment_link.expired' ? 'expired' : 'cancelled']);
                            $o->update(['payment_status' => 'failed']);
                        }
                    });
                }
            }
        }

        // Preserve legacy Standard Checkout webhook handling for existing payments.
        $legacyEntity = $event['payload']['payment']['entity'] ?? $event['payload']['order']['entity'] ?? null;
        if (is_array($legacyEntity) && !empty($legacyEntity['order_id'])) {
            $legacy = Payment::where('razorpay_order_id', $legacyEntity['order_id'])->with('order')->first();
            if ($legacy && in_array($type, ['payment.captured', 'order.paid', 'payment.failed'], true)) {
                DB::transaction(function () use ($legacy, $legacyEntity, $type) {
                    $p = Payment::whereKey($legacy->id)->lockForUpdate()->firstOrFail();
                    $o = Order::whereKey($p->order_id)->lockForUpdate()->firstOrFail();
                    if (in_array($type, ['payment.captured', 'order.paid'], true)
                        && (int) ($legacyEntity['amount'] ?? 0) === (int) round((float) $o->total_amount * 100)
                        && ($legacyEntity['currency'] ?? '') === 'INR') {
                        $p->update(['razorpay_payment_id' => $legacyEntity['id'] ?? $p->razorpay_payment_id, 'status' => 'paid', 'paid_at' => now()]);
                        $this->markOrderPaid($o);
                    } elseif ($type === 'payment.failed' && $p->status !== 'paid' && $o->payment_status !== 'paid') {
                        $p->update(['status' => 'failed', 'failure_reason' => Str::limit($legacyEntity['error_description'] ?? 'Payment failed', 500)]);
                        $o->update(['payment_status' => 'failed']);
                    }
                });
            }
        }

        if ($eventId) {
            DB::table('razorpay_webhook_events')->insertOrIgnore([
                'event_id' => $eventId,
                'event_type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['received' => true]);
    }

    private function fetchAndApplyPaymentLink(Payment $payment, ?string $callbackPaymentId = null): bool
    {
        $key = config('services.razorpay.key_id');
        $secret = config('services.razorpay.key_secret');
        if (!$key || !$secret) return false;

        $response = Http::withBasicAuth($key, $secret)->acceptJson()->timeout(15)
            ->get('https://api.razorpay.com/v1/payment_links/'.$payment->razorpay_payment_link_id);
        if (!$response->successful()) return false;

        $remote = $response->json();
        $order = $payment->order;
        if (($remote['id'] ?? null) !== $payment->razorpay_payment_link_id
            || ($remote['reference_id'] ?? null) !== $order->order_number
            || (int) ($remote['amount'] ?? 0) !== (int) round((float) $order->total_amount * 100)
            || ($remote['currency'] ?? null) !== $order->currency) {
            return false;
        }

        $captured = collect($remote['payments'] ?? [])->first(function ($item) use ($callbackPaymentId) {
            return ($item['status'] ?? null) === 'captured'
                && (!$callbackPaymentId || ($item['payment_id'] ?? null) === $callbackPaymentId);
        });

        if (($remote['status'] ?? '') !== 'paid' || !$captured) return false;

        DB::transaction(function () use ($payment, $captured) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $o = Order::whereKey($p->order_id)->lockForUpdate()->firstOrFail();
            if ((int) ($captured['amount'] ?? 0) !== (int) round((float) $o->total_amount * 100)) return;
            $paymentId = $captured['payment_id'] ?? null;
            if ($paymentId && $p->razorpay_payment_id && $p->razorpay_payment_id !== $paymentId) return;
            $p->update(['razorpay_payment_id' => $paymentId ?? $p->razorpay_payment_id, 'status' => 'paid', 'signature_verified_at' => now(), 'paid_at' => now()]);
            $this->markOrderPaid($o);
        });

        return true;
    }

    private function extractPaymentId(array $link): ?string
    {
        foreach (($link['payments'] ?? []) as $item) {
            if (($item['status'] ?? null) === 'captured' && !empty($item['payment_id'])) return $item['payment_id'];
        }
        return null;
    }

    private function markOrderPaid(Order $order): void
    {
        if ($order->payment_status !== 'paid') {
            $order->update([
                'payment_status' => 'paid',
                'order_status' => $order->order_status === 'pending_payment' ? 'confirmed' : $order->order_status,
                'paid_at' => now(),
                'confirmed_at' => $order->confirmed_at ?? now(),
            ]);
        }
    }

    public function status(Request $request, string $orderNumber)
    {
        $d = $request->validate(['token' => 'required|string|min:32|max:100']);
        $order = Order::where('order_number', $orderNumber)
            ->where('checkout_token_hash', hash('sha256', $d['token']))->firstOrFail();

        return response()->json(['success' => true, 'data' => [
            'order_number' => $order->order_number,
            'payment_status' => $order->payment_status,
            'order_status' => $order->order_status,
        ]]);
    }
}
