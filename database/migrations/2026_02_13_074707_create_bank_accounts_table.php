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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable();
            $table->string('account_number');
            $table->string('account_name');
            $table->string('bank_code');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable();
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('bank_account_id');
        });
        Schema::dropIfExists('bank_accounts');
    }
};
