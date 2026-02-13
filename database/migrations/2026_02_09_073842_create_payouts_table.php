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
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->string('provider_reference')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->string('mode', 20)->default('test');
            $table->string('status', 20)->default('pending');
            $table->string('reference', 100)->unique();
            $table->boolean('requires_otp')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->string('mode', 20)->default('live')->after('identifier');
            $table->boolean('is_payout_enabled')->default(false)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn(['mode', 'is_payout_enabled']);
        });

        Schema::dropIfExists('payouts');
    }
};
