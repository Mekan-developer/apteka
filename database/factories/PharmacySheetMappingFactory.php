<?php

namespace Database\Factories;

use App\Models\Pharmacy;
use App\Models\PharmacySheetMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PharmacySheetMapping>
 */
class PharmacySheetMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pharmacy_id' => Pharmacy::factory(),
            'header_row' => 4,
            'name_col' => 1,
            'price_col' => 2,
            'price_discounted_col' => null,
            'header_signature' => hash('sha256', '№|Наименование|Цена'),
        ];
    }
}
