<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) $table->string('phone', 20)->nullable()->index();
            if (!Schema::hasColumn('users', 'is_admin')) $table->boolean('is_admin')->default(false)->index();
        });
        // Guest customers can be stored without a password. Keep email nullable as guest/phone-only checkout is allowed.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_admin')) $table->dropColumn('is_admin');
            if (Schema::hasColumn('users', 'phone')) $table->dropColumn('phone');
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
