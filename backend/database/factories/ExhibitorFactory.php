<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Event;
use App\Models\Exhibitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exhibitor>
 */
class ExhibitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            // A category of the exhibitor's own event (the database rejects any other).
            'category_id' => fn (array $attributes) => Category::where('event_id', $attributes['event_id'])->value('id')
                ?? Category::factory()->create(['event_id' => $attributes['event_id']])->id,
            'name' => fake()->unique()->words(2, true),
            'short_description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function inCategory(Category $category): static
    {
        return $this->state(fn () => ['event_id' => $category->event_id, 'category_id' => $category->id]);
    }
}
