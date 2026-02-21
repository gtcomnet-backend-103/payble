<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('one_time_passwords', function (Blueprint $table) {
            $table->id();

            $table->string('password');
            $table->text('origin_properties')->nullable();

            $table->dateTime('expires_at');
            $table->morphs('authenticatable', 'one_time_passwords_authenticatable_type_authenticatable_id_index');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('one_time_passwords');
    }
};
