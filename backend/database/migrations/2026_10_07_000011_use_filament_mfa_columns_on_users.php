<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The admin panel's built-in TOTP MFA (Filament) stores its secret and
     * recovery codes in these columns, encrypted with the app key.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('two_factor_secret', 'app_authentication_secret');
            $table->dropColumn('two_factor_confirmed_at');
            $table->text('app_authentication_recovery_codes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('app_authentication_secret', 'two_factor_secret');
            $table->timestampTz('two_factor_confirmed_at')->nullable();
            $table->dropColumn('app_authentication_recovery_codes');
        });
    }
};
