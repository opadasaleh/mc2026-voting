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
        // One row per phone number (the visitor's UID), shared by all events.
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->text('phone_encrypted');
            // HMAC-SHA256 of the E.164 number: the blind index that enforces the UID.
            $table->char('phone_hash', 64)->unique();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
