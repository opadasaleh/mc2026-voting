<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The on-site rule is now venue Wi-Fi only (public IP allowlist): the
     * client-supplied geofence and the rule selector are removed.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE events DROP CONSTRAINT IF EXISTS events_access_mode_check');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['access_mode', 'geofence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('access_mode')->default('ip');
            $table->jsonb('geofence')->nullable();
        });

        DB::statement("ALTER TABLE events ADD CONSTRAINT events_access_mode_check CHECK (access_mode IN ('ip', 'geo', 'either', 'both'))");
    }
};
