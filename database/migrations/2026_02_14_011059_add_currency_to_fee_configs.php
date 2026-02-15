<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('fee_configs', 'currency')) {
                $table->string('currency', 3)->default('NGN')->after('channel');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fee_configs', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
