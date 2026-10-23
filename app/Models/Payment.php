<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['order_id', 'provider', 'razorpay_order_id', 'razorpay_payment_link_id', 'payment_link_url', 'razorpay_payment_id', 'amount', 'currency', 'status', 'signature_verified_at', 'paid_at', 'failure_reason'];
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'signature_verified_at' => 'datetime', 'paid_at' => 'datetime'];
    }
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
