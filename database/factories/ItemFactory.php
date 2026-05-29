<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'               => fake()->words(3, true),
            'category'           => 'Office Supplies',
            'unit'               => 'pcs',
            'total_qty_received' => fake()->numberBetween(10, 100),
            'current_qty'        => fake()->numberBetween(1, 10),
            'department_id'      => null,
        ];
    }
}
