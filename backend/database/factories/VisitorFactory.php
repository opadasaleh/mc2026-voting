<?php

namespace Database\Factories;

use App\Models\Visitor;
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
            // Test data only; the real HMAC blind index arrives with the OTP step.
            'phone_hash' => hash('sha256', $phone),
        ];
    }
}
