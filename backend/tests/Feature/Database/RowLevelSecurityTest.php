<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RowLevelSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Without RLS, Supabase's public REST API (anon key) could read the table, including visitor phone data.
     */
    public function test_every_public_table_has_row_level_security_enabled(): void
    {
        $unprotected = DB::select(<<<'SQL'
            SELECT c.relname
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p') AND NOT c.relrowsecurity
            ORDER BY c.relname
        SQL);

        $this->assertSame([], array_column($unprotected, 'relname'), 'Enable RLS in the migration that creates these tables.');
    }
}
