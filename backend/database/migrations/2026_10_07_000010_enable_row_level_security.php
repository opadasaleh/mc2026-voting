<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Supabase exposes the public schema through its REST API (anon / authenticated keys).
     * RLS with no policies makes that API return nothing, while Laravel connects as the
     * table owner and is unaffected. Tables added later must enable RLS in their own
     * migration; tests/Feature/Database/RowLevelSecurityTest fails if one is missed.
     */
    public function up(): void
    {
        foreach ($this->publicTables() as $table) {
            DB::statement("ALTER TABLE \"{$table}\" ENABLE ROW LEVEL SECURITY");
        }

        // Defence in depth on Supabase: the API roles get no table privileges at all.
        $apiRoles = array_column(
            DB::select("SELECT rolname FROM pg_roles WHERE rolname IN ('anon', 'authenticated')"),
            'rolname'
        );

        if ($apiRoles !== []) {
            $roles = implode(', ', $apiRoles);
            DB::statement("REVOKE ALL ON ALL TABLES IN SCHEMA public FROM {$roles}");
            DB::statement("REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM {$roles}");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->publicTables() as $table) {
            DB::statement("ALTER TABLE \"{$table}\" DISABLE ROW LEVEL SECURITY");
        }
    }

    /**
     * @return list<string>
     */
    private function publicTables(): array
    {
        return array_column(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'"), 'tablename');
    }
};
