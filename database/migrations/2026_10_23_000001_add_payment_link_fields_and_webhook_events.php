<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('user_id');
            $table->string('customer_email')->nullable()->after('customer_name');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('razorpay_payment_link_id')->nullable()->unique();
            $table->text('payment_link_url')->nullable();
        });

        Schema::create('razorpay_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('event_type', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('razorpay_webhook_events');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['razorpay_payment_link_id']);
            $table->dropColumn(['razorpay_payment_link_id', 'payment_link_url']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'customer_email']);
        });
    }
};
