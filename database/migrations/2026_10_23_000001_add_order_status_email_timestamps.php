<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // These fields are used by the existing checkout controller. Some
            // deployed databases may already have them, so add only if absent.
            if (!Schema::hasColumn('orders', 'customer_name')) {
                $table->string('customer_name')->nullable();
            }
            if (!Schema::hasColumn('orders', 'customer_email')) {
                $table->string('customer_email')->nullable()->index();
            }
            if (!Schema::hasColumn('orders', 'confirmed_email_sent_at')) {
                $table->timestamp('confirmed_email_sent_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'shipped_email_sent_at')) {
                $table->timestamp('shipped_email_sent_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'delivered_email_sent_at')) {
                $table->timestamp('delivered_email_sent_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Keep customer_name/customer_email because the existing checkout code
        // depends on them. Only remove this feature's notification timestamps.
        Schema::table('orders', function (Blueprint $table) {
            $columns = [];
            foreach (['confirmed_email_sent_at', 'shipped_email_sent_at', 'delivered_email_sent_at'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
