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
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('holder');
            $table->string('type');
            $table->string('currency', 3);
            $table->json('metadata')->nullable();
            $table->bigInteger('balance')->default(0);
            $table->timestamps();

            $table->unique(['currency', 'holder_id', 'holder_type', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};
