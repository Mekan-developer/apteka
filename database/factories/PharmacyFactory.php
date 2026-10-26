<?php

namespace Database\Factories;

use App\Models\Pharmacy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pharmacy>
 */
class PharmacyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'phones' => fake()->boolean() ? [fake()->numerify('+99365######')] : null,
            'address' => fake()->boolean() ? 'г. Ашгабат, ул. '.fake()->streetName().', дом '.fake()->buildingNumber() : null,
        ];
    }
}
