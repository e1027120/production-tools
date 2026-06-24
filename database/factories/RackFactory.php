<?php

namespace Database\Factories;

use App\Models\Rack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rack>
 */
class RackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucwords(fake()->words(2, true)) . ' Rack',
            'size' => fake()->randomElement([12, 16, 24, 42]),
            'description' => fake()->paragraph(),
            'devices' => [],
        ];
    }
}
