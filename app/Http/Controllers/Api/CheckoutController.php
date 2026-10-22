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
        $data = $request->validate(['idempotency_key' => 'required|string|max:100', 'customer.name' => 'required|string|max:150', 'customer.email' => 'nullable|email|max:255', 'customer.phone' => 'required|string|regex:/^[6-9][0-9]{9}$/', 'address.recipient_name' => 'required|string|max:150', 'address.phone' => 'required|string|regex:/^[6-9][0-9]{9}$/', 'address.address_line_1' => 'required|string|max:255', 'address.address_line_2' => 'nullable|string|max:255', 'address.landmark' => 'nullable|string|max:255', 'address.city' => 'required|string|max:100', 'address.state' => 'required|string|max:100', 'address.postal_code' => 'required|string|regex:/^[1-9][0-9]{5}$/', 'address.country' => 'sometimes|in:IN', 'customer_notes' => 'nullable|string|max:1000', 'items' => 'required|array|min:1|max:50', 'items.*.sku' => 'required|string|max:100', 'items.*.quantity' => 'required|integer|min:1|max:100']);
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
        // $shippingCents = ($subtotalCents >= (int)round((float)config('swaad.free_shipping_threshold', 999) * 100)) ? 0 : (int)round((float)config('swaad.shipping_fee', 60) * 100);
        $shippingCents = 0;
        $totalCents = $subtotalCents + $shippingCents;
        if ($totalCents < 100) throw ValidationException::withMessages(['items' => ['Order total must be at least ₹1.']]);
        $plainToken = Str::random(64);
        $order = null;
        try {
            $order = DB::transaction(function () use ($data, $lines, $subtotalCents, $shippingCents, $totalCents, $plainToken) {
                // Guest checkout: do not create a customer account or require authentication.
                $a = $data['address'];
                $order = Order::create(['order_number' => 'SWD-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)), 'idempotency_key' => $data['idempotency_key'], 'user_id' => null, 'customer_name' => $data['customer']['name'], 'customer_email' => $data['customer']['email'] ?? null, 'checkout_token_hash' => hash('sha256', $plainToken), 'recipient_name' => $a['recipient_name'], 'recipient_phone' => $a['phone'], 'delivery_address_line_1' => $a['address_line_1'], 'delivery_address_line_2' => $a['address_line_2'] ?? null, 'landmark' => $a['landmark'] ?? null, 'city' => $a['city'], 'state' => $a['state'], 'postal_code' => $a['postal_code'], 'country' => $a['country'] ?? 'IN', 'subtotal' => number_format($subtotalCents / 100, 2, '.', ''), 'shipping_fee' => 0, 'discount_amount' => '0.00', 'total_amount' => number_format($totalCents / 100, 2, '.', ''), 'currency' => 'INR', 'payment_status' => 'pending', 'order_status' => 'pending_payment', 'customer_notes' => $data['customer_notes'] ?? null]);
                foreach ($lines as $line) $order->items()->create(['product_id' => $line['sku'], 'product_variant_id' => $line['sku'], 'product_name' => $line['product']['name'], 'variant_name' => $line['product']['variant'] ?? null, 'quantity' => $line['quantity'], 'unit_price' => number_format($line['unit_cents'] / 100, 2, '.', ''), 'line_total' => number_format($line['unit_cents'] * $line['quantity'] / 100, 2, '.', '')]);
                return $order;
            });
        } catch (Throwable $e) {
            if (Order::where('idempotency_key', $data['idempotency_key'])->exists()) return response()->json(['message' => 'Checkout request already processed. Retry using the same idempotency key and X-Checkout-Token.'], 409);
            throw $e;
        }
        $key = config('services.razorpay.key_id');
        $secret = config('services.razorpay.key_secret');
        if (!$key || !$secret) {
            $order->update(['payment_status' => 'failed']);
            return response()->json(['message' => 'Payment gateway is not configured.'], 503);
        }
        try {
            $r = Http::withBasicAuth($key, $secret)->timeout(15)->post('https://api.razorpay.com/v1/orders', ['amount' => $totalCents, 'currency' => 'INR', 'receipt' => $order->order_number, 'notes' => ['local_order_number' => $order->order_number]]);
            if (!$r->successful()) throw new \RuntimeException('Razorpay order creation failed.');
            $rz = $r->json();
            $order->payments()->create(['provider' => 'razorpay', 'razorpay_order_id' => $rz['id'], 'amount' => $order->total_amount, 'currency' => 'INR', 'status' => 'created']);
        } catch (Throwable $e) {
            report($e);
            $order->update(['payment_status' => 'failed']);
            return response()->json(['message' => 'Unable to initialize payment. Please contact support with order number ' . $order->order_number . '.'], 502);
        }


        
        return response()->json(['success' => true, 'data' => ['order_number' => $order->order_number, 'checkout_token' => $plainToken, 'razorpay' => ['key_id' => $key, 'order_id' => $rz['id'], 'amount' => $totalCents, 'currency' => 'INR'], 'amounts' => ['subtotal' => $order->subtotal, 'shipping_fee' => 0, 'discount_amount' => $order->discount_amount, 'total_amount' => $order->total_amount]]], 201);
    }
    private function checkoutResponse(Order $order, ?string $token)
    {
        if (!$token || !hash_equals($order->checkout_token_hash, hash('sha256', $token))) return response()->json(['message' => 'Idempotency key has already been used.'], 409);
        $payment = $order->payments()->latest()->first();
        if (!$payment) return response()->json(['message' => 'Payment initialization requires support.'], 409);
        return response()->json(['success' => true, 'data' => ['order_number' => $order->order_number, 'checkout_token' => $token, 'razorpay' => ['key_id' => config('services.razorpay.key_id'), 'order_id' => $payment->razorpay_order_id, 'amount' => (int)round((float)$order->total_amount * 100), 'currency' => $order->currency], 'amounts' => ['subtotal' => $order->subtotal, 'shipping_fee' =>0, 'discount_amount' => $order->discount_amount, 'total_amount' => $order->total_amount]]]);
    }
}
