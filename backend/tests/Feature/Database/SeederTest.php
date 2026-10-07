<?php

namespace Tests\Feature\Database;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_twice_creates_mc2026_once_in_a_safe_state(): void
    {
        $this->seed();
        $this->seed();

        $event = Event::where('slug', 'mc2026')->sole();

        $this->assertFalse($event->voting_enabled, 'Voting must start closed.');
        $this->assertSame('ip', $event->access_mode);
        $this->assertSame([], $event->allowed_cidrs, 'No venue IPs until an admin sets them.');
        $this->assertSame(3, $event->categories()->count());
        $this->assertSame(9, $event->exhibitors()->count());
        $this->assertSame([3, 3, 3], $event->categories()->withCount('exhibitors')->pluck('exhibitors_count')->all());
        $this->assertSame(1, User::where('username', 'admin')->count());
    }
}
