<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'is_active' => true,
            'voting_enabled' => false,
            'access_mode' => 'ip',
            'allowed_cidrs' => ['203.0.113.0/24'],
        ];
    }

    public function votingOpen(): static
    {
        return $this->state(fn () => ['voting_enabled' => true]);
    }
}
