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
        Schema::create('ledger_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->unique()->constrained()->onDelete('cascade');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('account_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_account_id')->unique()->constrained('ledger_accounts')->onDelete('cascade');
            $table->bigInteger('balance')->default(0);
            $table->unsignedBigInteger('last_entry_id')->default(0);
            $table->timestamps();
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('ledger_entries', 'ledger_batch_id')) {
                $table->foreignId('ledger_batch_id')->nullable()->constrained('ledger_batches')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropForeign(['ledger_batch_id']);
            $table->dropColumn('ledger_batch_id');
        });

        Schema::dropIfExists('account_balances');
        Schema::dropIfExists('ledger_batches');
    }
};
