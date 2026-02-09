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
        Schema::table('ledger_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('ledger_accounts', 'mode')) {
                $table->string('mode', 20)->default('live')->after('currency');
            }

            // Drop old unique if it exists
            $table->dropUnique('ledger_accounts_currency_holder_id_holder_type_type_unique');

            // Add new unique
            $table->unique(['currency', 'holder_id', 'holder_type', 'type', 'mode'], 'ledger_accounts_unique_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropUnique('ledger_accounts_unique_index');
            $table->unique(['currency', 'holder_id', 'holder_type', 'type'], 'ledger_accounts_currency_holder_id_holder_type_type_unique');
            $table->dropColumn('mode');
        });
    }
};
