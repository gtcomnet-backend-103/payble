<?php

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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_intent_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->string('status');
            $table->string('reference')->unique();
            $table->string('mode');
            $table->string('channel')->nullable();
            $table->string('ip_address')->nullable();
            $table->unsignedBigInteger('fees')->default(0);
            $table->json('metadata')->nullable();
            $table->json('authorization')->nullable();
            $table->string('message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
