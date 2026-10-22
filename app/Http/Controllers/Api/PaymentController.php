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
        $d = $request->validate(['order_number' => 'required|string', 'razorpay_order_id' => 'required|string', 'razorpay_payment_id' => 'required|string', 'razorpay_signature' => 'required|string']);
        $order = Order::where('order_number', $d['order_number'])->with('payments')->firstOrFail();
        $payment = $order->payments()->where('razorpay_order_id', $d['razorpay_order_id'])->firstOrFail();
        if ($payment->razorpay_payment_id && $payment->razorpay_payment_id !== $d['razorpay_payment_id']) return response()->json(['message' => 'Payment ID does not match this attempt.'], 422);
        $expected = hash_hmac('sha256', $d['razorpay_order_id'] . '|' . $d['razorpay_payment_id'], config('services.razorpay.key_secret'));
        if (!hash_equals($expected, $d['razorpay_signature'])) return response()->json(['message' => 'Invalid payment signature.'], 422);
        $response = Http::withBasicAuth(config('services.razorpay.key_id'), config('services.razorpay.key_secret'))->timeout(15)->get('https://api.razorpay.com/v1/payments/' . $d['razorpay_payment_id']);
        if (!$response->successful()) return response()->json(['message' => 'Payment status could not be confirmed. Please wait for confirmation.'], 503);
        $remote = $response->json();
        if (($remote['order_id'] ?? null) !== $payment->razorpay_order_id || (int)($remote['amount'] ?? 0) !== (int)round((float)$order->total_amount * 100) || ($remote['currency'] ?? null) !== 'INR') return response()->json(['message' => 'Payment details do not match the order.'], 422);
        DB::transaction(function () use ($order, $payment, $d, $remote) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (($remote['status'] ?? '') === 'captured' || ($remote['status'] ?? '') === 'authorized' && ($remote['captured'] ?? false)) {
                $p->update(['razorpay_payment_id' => $d['razorpay_payment_id'], 'status' => 'paid', 'signature_verified_at' => now(), 'paid_at' => now()]);
                $o = Order::whereKey($order->id)->lockForUpdate()->first();
                if ($o->payment_status !== 'paid') $o->update(['payment_status' => 'paid', 'order_status' => $o->order_status === 'pending_payment' ? 'confirmed' : $o->order_status, 'paid_at' => now(), 'confirmed_at' => now()]);
            } elseif (($remote['status'] ?? '') === 'failed') {
                if ($p->status !== 'paid') $p->update(['razorpay_payment_id' => $d['razorpay_payment_id'], 'status' => 'failed', 'signature_verified_at' => now(), 'failure_reason' => Str::limit($remote['error_description'] ?? 'Payment failed', 500)]);
                if ($order->payment_status !== 'paid') $order->update(['payment_status' => 'failed']);
            } else {
                $p->update(['razorpay_payment_id' => $d['razorpay_payment_id'], 'status' => 'pending', 'signature_verified_at' => now()]);
            }
        });
        $order->refresh();
        return response()->json(['success' => true, 'data' => ['order_number' => $order->order_number, 'payment_status' => $order->payment_status, 'order_status' => $order->order_status]]);
    }
    public function webhook(Request $request)
    {
        $secret = config('services.razorpay.webhook_secret');
        $signature = $request->header('X-Razorpay-Signature');
        if (!$secret || !$signature || !hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature)) return response()->json(['message' => 'Invalid webhook signature.'], 400);
        $event = $request->json()->all();
        $type = $event['event'] ?? '';
        $entity = $event['payload']['payment']['entity'] ?? $event['payload']['order']['entity'] ?? null;
        if (!$entity) return response()->json(['received' => true]);
        $paymentId = str_starts_with($type, 'payment.') ? ($entity['id'] ?? null) : null;
        $razorpayOrderId = $entity['order_id'] ?? ($entity['id'] ?? null);
        $payment = Payment::where('razorpay_order_id', $razorpayOrderId)->first();
        if (!$payment && $paymentId) $payment = Payment::where('razorpay_payment_id', $paymentId)->first();
        if (!$payment) return response()->json(['received' => true]);
        DB::transaction(function () use ($payment, $entity, $type, $paymentId) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->first();
            $o = Order::whereKey($p->order_id)->lockForUpdate()->first();
            if ($type === 'payment.captured' || $type === 'order.paid') {
                if ((int)($entity['amount'] ?? round((float)$o->total_amount * 100)) === (int)round((float)$o->total_amount * 100) && ($entity['currency'] ?? 'INR') === 'INR') {
                    $p->update(['razorpay_payment_id' => $paymentId ?? $p->razorpay_payment_id, 'status' => 'paid', 'paid_at' => now()]);
                    if ($o->payment_status !== 'paid') $o->update(['payment_status' => 'paid', 'order_status' => $o->order_status === 'pending_payment' ? 'confirmed' : $o->order_status, 'paid_at' => now(), 'confirmed_at' => now()]);
                }
            } elseif ($type === 'payment.failed' && $o->payment_status !== 'paid' && $p->status !== 'paid') {
                $p->update(['razorpay_payment_id' => $paymentId ?? $p->razorpay_payment_id, 'status' => 'failed', 'failure_reason' => \Illuminate\Support\Str::limit($entity['error_description'] ?? 'Payment failed', 500)]);
                $o->update(['payment_status' => 'failed']);
            }
        });
        return response()->json(['received' => true]);
    }
    public function status(Request $request, string $orderNumber)
    {
        $d = $request->validate(['token' => 'required|string|min:32|max:100']);
        $order = Order::where('order_number', $orderNumber)->where('checkout_token_hash', hash('sha256', $d['token']))->firstOrFail();
        return response()->json(['success' => true, 'data' => ['order_number' => $order->order_number, 'payment_status' => $order->payment_status, 'order_status' => $order->order_status]]);
    }
}
