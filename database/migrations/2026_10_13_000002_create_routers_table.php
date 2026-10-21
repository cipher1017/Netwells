<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('identity')->unique();
            $table->string('host');
            $table->unsignedInteger('api_port')->default(8728);
            $table->string('api_user');
            $table->text('api_password'); // encrypted at the model layer
            $table->string('hotspot_server');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routers');
    }
};
