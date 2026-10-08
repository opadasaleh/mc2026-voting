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
        // A visitor's participation in one event; OTP verification is per event.
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('visitor_id')->constrained()->restrictOnDelete();
            $table->string('full_name');
            $table->timestampTz('phone_verified_at')->nullable();
            $table->ipAddress('registered_ip')->nullable();
            $table->timestampsTz();

            // Also the target of the votes composite foreign key.
            $table->unique(['event_id', 'visitor_id']);
            $table->index('visitor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
