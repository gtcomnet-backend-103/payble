<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('authorization_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_intent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('provider_reference');
            $table->string('status')->default('pending');
            $table->integer('fee');
            $table->integer('provider_fee')->default(0);
            $table->integer('amount');
            $table->string('currency');
            $table->string('idempotency_key')->unique();
            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['payment_intent_id', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authorization_attempts');
    }
};
