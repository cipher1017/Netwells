<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('hotspot_username');
            $table->text('hotspot_password'); // encrypted at the model layer
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->string('status')->default('active'); // active | expired | revoked
            $table->timestamps();

            $table->index(['status', 'expires_at']); // expiry sweep
            $table->index(['device_id', 'status']);   // "active sub for this device?"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
