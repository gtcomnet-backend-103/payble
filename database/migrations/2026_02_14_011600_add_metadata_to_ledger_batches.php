<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_batches', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('ledger_batches', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
