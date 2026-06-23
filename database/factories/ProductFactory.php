<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word() . ' ' . fake()->word(),
            'description' => fake()->word() . ' ' . fake()->word() . ' ' . fake()->word(),
            'price' => fake()->randomFloat(2, 1, 350),
            'status' => 'active',
        ];
    }
}
