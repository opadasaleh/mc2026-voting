<?php

namespace Database\Factories;

use App\Models\Visitor;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phone = '+96279'.fake()->unique()->numerify('#######');

        return [
            'full_name' => fake()->name(),
            'phone_encrypted' => $phone,
            'phone_hash' => PhoneNumber::hash($phone),
        ];
    }
}
