<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_kes');
            $table->string('payment_method')->default('free'); // free | mpesa
            $table->string('status')->default('pending');      // pending | paid | failed
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('activated_at')->nullable();      // idempotency guard for ActivateOrder
            $table->timestamps();

            $table->index(['device_id', 'payment_method', 'created_at']); // cooldown lookups
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
