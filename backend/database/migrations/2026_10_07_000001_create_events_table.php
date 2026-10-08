<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // One row per voting event, including its database-driven configuration.
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('voting_enabled')->default(false);
            $table->timestampTz('opens_at')->nullable();
            $table->timestampTz('closes_at')->nullable();
            $table->string('access_mode')->default('ip');
            $table->jsonb('allowed_cidrs')->default(new Expression("'[]'::jsonb"));
            $table->jsonb('geofence')->nullable();
            $table->string('venue_wifi_name')->nullable();
            $table->integer('otp_ttl_seconds')->default(300);
            $table->integer('otp_max_attempts')->default(5);
            $table->integer('otp_resend_cooldown_seconds')->default(60);
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE events ADD CONSTRAINT events_access_mode_check CHECK (access_mode IN ('ip', 'geo', 'either', 'both'))");
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_voting_window_check CHECK (opens_at IS NULL OR closes_at IS NULL OR closes_at > opens_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
