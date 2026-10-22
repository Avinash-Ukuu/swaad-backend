<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['order_number', 'idempotency_key', 'user_id', 'customer_name', 'customer_email', 'checkout_token_hash', 'recipient_name', 'recipient_phone', 'delivery_address_line_1', 'delivery_address_line_2', 'landmark', 'city', 'state', 'postal_code', 'country', 'subtotal', 'shipping_fee', 'discount_amount', 'total_amount', 'currency', 'payment_status', 'order_status', 'shipping_provider', 'shipping_id', 'tracking_url', 'customer_notes', 'internal_notes', 'paid_at', 'confirmed_at', 'shipped_at', 'delivered_at'];
    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'shipping_fee' => 'decimal:2', 'discount_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'paid_at' => 'datetime', 'confirmed_at' => 'datetime', 'shipped_at' => 'datetime', 'delivered_at' => 'datetime'];
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    public function latestPayment()
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }
}
