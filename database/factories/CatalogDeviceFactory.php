<?php

namespace Database\Factories;

use App\Models\CatalogDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogDevice>
 */
class CatalogDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand' => $this->faker->company(),
            'name' => $this->faker->word() . ' ' . $this->faker->numerify('###'),
            'type' => $this->faker->randomElement(['Audio', 'Video', 'Projection', 'Network', 'Power']),
            'u_height' => $this->faker->numberBetween(1, 4),
            'power_consumption' => $this->faker->numberBetween(10, 500),
            'weight' => $this->faker->randomFloat(2, 0.5, 15.0),
        ];
    }
}
