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
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->bigInteger('balance')->default(0)->after('currency');
        });

        // Initialize balances for existing accounts from ledger entries
        \Illuminate\Support\Facades\DB::table('ledger_accounts')->get()->each(function (stdClass $account) {
            $balance = \Illuminate\Support\Facades\DB::table('ledger_entries')
                ->where('ledger_account_id', $account->id)
                ->sum('amount');

            \Illuminate\Support\Facades\DB::table('ledger_accounts')
                ->where('id', $account->id)
                ->update(['balance' => $balance]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
