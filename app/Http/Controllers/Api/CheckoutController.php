<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Order, OrderItem, Payment};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Http};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['idempotency_key' => 'required|string|max:100', 'customer.name' => 'required|string|max:150', 'customer.email' => 'nullable|email|max:255', 'customer.phone' => 'required|string|regex:/^[6-9][0-9]{9}$/', 'address.recipient_name' => 'required|string|max:150', 'address.phone' => 'required|string|regex:/^[6-9][0-9]{9}$/', 'address.address_line_1' => 'required|string|max:255', 'address.address_line_2' => 'nullable|string|max:255', 'address.landmark' => 'nullable|string|max:255', 'address.city' => 'required|string|max:100', 'address.state' => 'required|string|max:100', 'address.postal_code' => 'required|string|regex:/^[1-9][0-9]{5}$/', 'address.country' => 'sometimes|in:IN', 'customer_notes' => 'nullable|string|max:1000', 'items' => 'required|array|min:1|max:50', 'items.*.sku' => 'required|string|max:100', 'items.*.quantity' => 'required|integer|min:1|max:100', 'coupon_code' => 'nullable|string|max:50|regex:/^[A-Za-z0-9_-]+$/']);
        if ($existing = Order::where('idempotency_key', $data['idempotency_key'])->with('payments')->first()) return $this->checkoutResponse($existing, $request->header('X-Checkout-Token'));
        $catalog = config('swaad_catalog');
        $subtotalCents = 0;
        $lines = [];
        foreach ($data['items'] as $item) {
            $p = $catalog[$item['sku']] ?? null;
            if (!$p || empty($p['available'])) throw ValidationException::withMessages(['items' => ['One or more products are unavailable.']]);
            $price = (int) round(((float)$p['price']) * 100);
            $subtotalCents += $price * $item['quantity'];
            $lines[] = ['sku' => $item['sku'], 'product' => $p, 'quantity' => $item['quantity'], 'unit_cents' => $price];
        }
        // Keep the existing shipping behavior unchanged. Coupon discounts are calculated
        // exclusively from trusted catalogue prices and the server-side subtotal.
        $shippingCents = 0;
        $couponCode = null;
        $discountCents = 0;
        if (!empty($data['coupon_code'])) {
            $couponCode = strtoupper(trim($data['coupon_code']));
            $coupon = (config('coupons.codes', []))[$couponCode] ?? null;
            if (!$coupon) throw ValidationException::withMessages(['coupon_code' => ['This coupon code is invalid.']]);
            if (empty($coupon['active'])) throw ValidationException::withMessages(['coupon_code' => ['This coupon is currently inactive.']]);
            $minimumCents = (int) round(((float) ($coupon['min_order_amount'] ?? 0)) * 100);
            if ($subtotalCents < $minimumCents) throw ValidationException::withMessages(['coupon_code' => ['Your order does not meet the minimum amount for this coupon.']]);
            $type = $coupon['type'] ?? '';
            $value = (float) ($coupon['value'] ?? 0);
            if ($value <= 0 || !in_array($type, ['fixed', 'percentage'], true) || ($type === 'percentage' && $value > 100)) {
                throw ValidationException::withMessages(['coupon_code' => ['This coupon is not configured correctly.']]);
            }
            $discountCents = $type === 'percentage'
                ? (int) round($subtotalCents * $value / 100)
                : (int) round($value * 100);
            if (isset($coupon['max_discount']) && $coupon['max_discount'] !== null) {
                $discountCents = min($discountCents, (int) round(((float) $coupon['max_discount']) * 100));
            }
            // Razorpay's existing checkout requires at least ₹1 payable.
            $discountCents = min($discountCents, max(0, $subtotalCents - 100));
        }
        $totalCents = $subtotalCents + $shippingCents - $discountCents;
        if ($totalCents < 100) throw ValidationException::withMessages(['items' => ['Order total must be at least ₹1.']]);
        $plainToken = Str::random(64);
        $order = null;
        try {
            $order = DB::transaction(function () use ($data, $lines, $subtotalCents, $shippingCents, $discountCents, $couponCode, $totalCents, $plainToken) {
                // Guest checkout: do not create a customer account or require authentication.
                $a = $data['address'];
                $order = Order::create(['order_number' => 'SWD-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)), 'idempotency_key' => $data['idempotency_key'], 'user_id' => null, 'customer_name' => $data['customer']['name'], 'customer_email' => $data['customer']['email'] ?? null, 'checkout_token_hash' => hash('sha256', $plainToken), 'recipient_name' => $a['recipient_name'], 'recipient_phone' => $a['phone'], 'delivery_address_line_1' => $a['address_line_1'], 'delivery_address_line_2' => $a['address_line_2'] ?? null, 'landmark' => $a['landmark'] ?? null, 'city' => $a['city'], 'state' => $a['state'], 'postal_code' => $a['postal_code'], 'country' => $a['country'] ?? 'IN', 'subtotal' => number_format($subtotalCents / 100, 2, '.', ''), 'shipping_fee' => number_format($shippingCents / 100, 2, '.', ''), 'coupon_code' => $couponCode, 'discount_amount' => number_format($discountCents / 100, 2, '.', ''), 'total_amount' => number_format($totalCents / 100, 2, '.', ''), 'currency' => 'INR', 'payment_status' => 'pending', 'order_status' => 'pending_payment', 'customer_notes' => $data['customer_notes'] ?? null]);
                foreach ($lines as $line) $order->items()->create(['product_id' => $line['sku'], 'product_variant_id' => $line['sku'], 'product_name' => $line['product']['name'], 'variant_name' => $line['product']['variant'] ?? null, 'quantity' => $line['quantity'], 'unit_price' => number_format($line['unit_cents'] / 100, 2, '.', ''), 'line_total' => number_format($line['unit_cents'] * $line['quantity'] / 100, 2, '.', '')]);
                return $order;
            });
        } catch (Throwable $e) {
            if (Order::where('idempotency_key', $data['idempotency_key'])->exists()) return response()->json(['message' => 'Checkout request already processed. Retry using the same idempotency key and X-Checkout-Token.'], 409);
            throw $e;
        }
        return $this->createOrReturnPaymentLink($order, $plainToken, 201);
    }

    private function createOrReturnPaymentLink(Order $order, string $token, int $createdStatus = 200)
    {
        $key = config('services.razorpay.key_id');
        $secret = config('services.razorpay.key_secret');

        if (!$key || !$secret) {
            return response()->json(['message' => 'Payment gateway is not configured.'], 503);
        }

        // Lock this order while checking/creating its link. Never retry a timed-out
        // non-idempotent Razorpay request automatically: the remote link may exist.
        try {
            $result = DB::transaction(function () use ($order, $token, $key, $secret, $createdStatus) {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $payment = $lockedOrder->payments()->whereNotNull('razorpay_payment_link_id')->latest()->first();
                $unknownAttempt = $lockedOrder->payments()->where('status', 'creation_unknown')->latest()->first();
                if ($unknownAttempt && !$payment) {
                    return ['response' => response()->json(['message' => 'Payment link creation needs reconciliation before retrying. Please contact support with order number '.$lockedOrder->order_number.'.'], 503), 'status' => 503];
                }

                if ($payment && $payment->payment_link_url && in_array($payment->status, ['created', 'pending'], true)) {
                    return ['response' => $this->paymentLinkResponse($lockedOrder, $payment, $token), 'status' => 200];
                }
                if ($payment && $payment->status === 'creation_unknown') {
                    return ['response' => response()->json(['message' => 'Payment link creation needs reconciliation before retrying. Please contact support with order number '.$lockedOrder->order_number.'.'], 503), 'status' => 503];
                }
                if ($lockedOrder->payment_status === 'paid') {
                    return ['response' => response()->json(['message' => 'This order has already been paid.'], 409), 'status' => 409];
                }

                $customer = [
                    'name' => $lockedOrder->customer_name,
                    'contact' => '+91'.$lockedOrder->recipient_phone,
                ];
                if ($lockedOrder->customer_email) $customer['email'] = $lockedOrder->customer_email;

                // A row is persisted before the remote call, so an interrupted request
                // is visible and cannot silently generate another link on retry.
                $payment = $lockedOrder->payments()->create([
                    'provider' => 'razorpay',
                    'amount' => $lockedOrder->total_amount,
                    'currency' => $lockedOrder->currency,
                    'status' => 'creating',
                ]);

                try {
                    $response = Http::withBasicAuth($key, $secret)
                        ->acceptJson()
                        ->asJson()
                        ->connectTimeout(5)
                        ->timeout(15)
                        ->post('https://api.razorpay.com/v1/payment_links', [
                            'amount' => (int) round((float) $lockedOrder->total_amount * 100),
                            'currency' => $lockedOrder->currency,
                            'reference_id' => $lockedOrder->order_number,
                            'description' => 'SWAAD order '.$lockedOrder->order_number,
                            'customer' => $customer,
                            'notify' => ['sms' => false, 'email' => false],
                            'reminder_enable' => false,
                            'callback_url' => route('payments.callback'),
                            'callback_method' => 'get',
                            'notes' => ['local_order_number' => $lockedOrder->order_number],
                        ]);
                } catch (Throwable $e) {
                    $payment->update(['status' => 'creation_unknown']);
                    report($e);
                    return ['response' => response()->json(['message' => 'Payment link creation could not be confirmed. Do not retry with a new checkout. Contact support with order number '.$lockedOrder->order_number.'.'], 502), 'status' => 502];
                }

                if (!$response->successful()) {
                    // An explicit gateway rejection is safe to retry; no raw response is returned.
                    $payment->update(['status' => 'failed', 'failure_reason' => 'Razorpay rejected Payment Link creation.']);
                    return ['response' => response()->json(['message' => 'Unable to create payment link. Please retry checkout or contact support with order number '.$lockedOrder->order_number.'.'], 502), 'status' => 502];
                }

                $gateway = $response->json();
                if (empty($gateway['id']) || empty($gateway['short_url'])) {
                    $payment->update(['status' => 'creation_unknown']);
                    return ['response' => response()->json(['message' => 'Payment link creation could not be confirmed. Contact support with order number '.$lockedOrder->order_number.'.'], 502), 'status' => 502];
                }

                $payment->update([
                    'razorpay_payment_link_id' => $gateway['id'],
                    'payment_link_url' => $gateway['short_url'],
                    'status' => 'created',
                ]);

                return ['response' => $this->paymentLinkResponse($lockedOrder, $payment, $token), 'status' => $createdStatus];
            });

            return $result['response']->setStatusCode($result['status']);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'Unable to initialize payment. Please contact support with order number '.$order->order_number.'.'], 502);
        }
    }

    private function paymentLinkResponse(Order $order, Payment $payment, string $token)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'order_number' => $order->order_number,
                'checkout_token' => $token,
                'payment_link' => $payment->payment_link_url,
                'payment_status' => $order->payment_status,
                'order_status' => $order->order_status,
                'amounts' => [
                    'subtotal' => $order->subtotal,
                    'shipping_fee' => $order->shipping_fee,
                    'discount_amount' => $order->discount_amount,
                    'total_amount' => $order->total_amount,
                ],
                'currency' => $order->currency,
            ],
        ]);
    }

    private function checkoutResponse(Order $order, ?string $token)
    {
        if (!$token || !hash_equals($order->checkout_token_hash, hash('sha256', $token))) {
            return response()->json(['message' => 'Idempotency key has already been used.'], 409);
        }

        $payment = $order->payments()->latest()->first();
        if ($payment && $payment->payment_link_url && in_array($payment->status, ['created', 'pending'], true)) {
            return $this->paymentLinkResponse($order, $payment, $token);
        }
        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'This order has already been paid.'], 409);
        }
        if ($payment && $payment->status === 'creation_unknown') {
            return response()->json(['message' => 'Payment link creation needs reconciliation. Contact support with order number '.$order->order_number.'.'], 503);
        }

        return $this->createOrReturnPaymentLink($order, $token);
    }
}
