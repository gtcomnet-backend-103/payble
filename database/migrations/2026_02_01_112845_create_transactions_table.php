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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->string('status');
            $table->string('reference');
            $table->string('mode');
            $table->string('channel')->nullable();
            $table->unsignedBigInteger('fees')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'reference']);
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
