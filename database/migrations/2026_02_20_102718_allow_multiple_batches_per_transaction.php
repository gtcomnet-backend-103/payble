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
        Schema::table('ledger_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('ledger_batches', 'name')) {
                $table->string('name')->default('default')->after('transaction_id');
            }

            // Drop foreign key first for some DBs
            try {
                $table->dropForeign(['transaction_id']);
            } catch (Exception $e) {
            }

            try {
                $table->dropUnique(['transaction_id']);
            } catch (Exception $e) {
            }

            $table->foreignId('transaction_id')->nullable()->change()->constrained()->onDelete('cascade');

            // Unique index on transaction_id and name
            try {
                $table->unique(['transaction_id', 'name']);
            } catch (Exception $e) {
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledger_batches', function (Blueprint $table) {
            $table->dropUnique(['transaction_id', 'name']);
            $table->dropColumn('name');
            $table->unique('transaction_id');
        });
    }
};
