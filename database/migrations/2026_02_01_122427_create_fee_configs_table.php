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
        Schema::create('fee_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('currency')->default('NGN');
            $table->integer('min_fee')->default(0);
            $table->integer('max_fee')->nullable();
            $table->decimal('percentage', 5, 2)->default(0);
            $table->integer('fixed_amount')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_configs');
    }
};
