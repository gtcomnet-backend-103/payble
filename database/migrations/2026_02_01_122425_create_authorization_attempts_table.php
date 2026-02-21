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
            $table->morphs('intent');
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('provider_reference');
            $table->string('status')->default('pending');
            $table->integer('fee');
            $table->integer('provider_fee')->default(0);
            $table->integer('amount');
            $table->string('currency');
            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->index();
            $table->timestamps();
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
