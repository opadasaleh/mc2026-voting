<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against a real Supabase database: RefreshDatabase wipes it.
     * Runs before any test trait touches the database.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $host = (string) $app['config']->get("database.connections.{$connection}.host");

        if (str_contains($host, 'supabase')) {
            throw new RuntimeException("Tests must not run against Supabase ({$host}). Use the local test-db from compose.yaml.");
        }

        return $app;
    }
}
