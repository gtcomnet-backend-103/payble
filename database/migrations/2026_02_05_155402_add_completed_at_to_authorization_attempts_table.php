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
        Schema::table('authorization_attempts', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
            $table->index('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('authorization_attempts', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
