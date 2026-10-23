<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentLinkCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.razorpay.key_id' => 'rzp_test_key',
            'services.razorpay.key_secret' => 'test_secret',
            'services.razorpay.webhook_secret' => 'test_webhook_secret',
            'services.razorpay.frontend_url' => 'https://store.example.test',
        ]);
    }

    private function payload(string $key = 'checkout-test-1'): array
    {
        return [
            'idempotency_key' => $key,
            'customer' => ['name' => 'Test Customer', 'email' => 'customer@example.test', 'phone' => '9876543210'],
            'address' => [
                'recipient_name' => 'Test Customer',
                'phone' => '9876543210',
                'address_line_1' => '1 Test Street',
                'city' => 'Jalandhar',
                'state' => 'Punjab',
                'postal_code' => '144001',
                'country' => 'IN',
            ],
            'items' => [['sku' => 'ajwain-honey-250g', 'quantity' => 2]],
        ];
    }

    public function test_checkout_creates_order_and_returns_actual_payment_link_url(): void
    {
        Http::fake([
            'api.razorpay.com/v1/payment_links' => Http::response([
                'id' => 'plink_test_123',
                'short_url' => 'https://rzp.io/i/real-test-link',
                'status' => 'created',
            ], 200),
        ]);

        $response = $this->withHeader('X-Checkout-Token', '')->postJson('/api/checkout', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.payment_link', 'https://rzp.io/i/real-test-link')
            ->assertJsonPath('data.amounts.total_amount', '700.00')
            ->assertJsonPath('data.currency', 'INR');

        $this->assertDatabaseHas('orders', ['order_number' => $response->json('data.order_number'), 'payment_status' => 'pending']);
        $this->assertDatabaseHas('payments', ['razorpay_payment_link_id' => 'plink_test_123', 'payment_link_url' => 'https://rzp.io/i/real-test-link']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.razorpay.com/v1/payment_links'
            && $request['amount'] === 70000
            && $request['currency'] === 'INR'
            && $request['reference_id'] === $response->json('data.order_number'));
    }

    public function test_repeated_checkout_with_token_reuses_existing_link(): void
    {
        Http::fake(['api.razorpay.com/v1/payment_links' => Http::response([
            'id' => 'plink_test_456', 'short_url' => 'https://rzp.io/i/reused', 'status' => 'created',
        ], 200)]);

        $first = $this->postJson('/api/checkout', $this->payload());
        $first->assertCreated();
        $token = $first->json('data.checkout_token');
        $key = $first->json('data.order_number');

        $payload = $this->payload();
        $payload['idempotency_key'] = 'checkout-test-1';
        $second = $this->withHeader('X-Checkout-Token', $token)->postJson('/api/checkout', $payload);

        $second->assertOk()->assertJsonPath('data.payment_link', 'https://rzp.io/i/reused');
        $this->assertSame(1, Order::count());
        Http::assertSentCount(1);
    }

    public function test_gateway_rejection_does_not_mark_order_paid_or_expose_gateway_body(): void
    {
        Http::fake(['api.razorpay.com/v1/payment_links' => Http::response(['error' => ['description' => 'private gateway detail']], 400)]);

        $response = $this->postJson('/api/checkout', $this->payload());

        $response->assertStatus(502)->assertJsonMissing(['private gateway detail']);
        $this->assertDatabaseHas('orders', ['payment_status' => 'pending']);
        $this->assertDatabaseHas('payments', ['status' => 'failed']);
    }

    public function test_invalid_webhook_signature_is_rejected(): void
    {
        $this->call('POST', '/api/payments/webhook', [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => 'invalid',
            'CONTENT_TYPE' => 'application/json',
        ], '{"event":"payment_link.paid"}')->assertStatus(400);
    }

    public function test_order_status_endpoint_requires_matching_checkout_token(): void
    {
        Http::fake(['api.razorpay.com/v1/payment_links' => Http::response([
            'id' => 'plink_test_status', 'short_url' => 'https://rzp.io/i/status', 'status' => 'created',
        ], 200)]);

        $checkout = $this->postJson('/api/checkout', $this->payload());
        $number = $checkout->json('data.order_number');
        $token = $checkout->json('data.checkout_token');

        $this->getJson('/api/orders/'.$number.'/status?token='.$token)
            ->assertOk()->assertJsonPath('data.payment_status', 'pending');
        $this->getJson('/api/orders/'.$number.'/status?token=wrong-token')
            ->assertUnprocessable();
    }
}
