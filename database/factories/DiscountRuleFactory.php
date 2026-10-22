<?php

namespace Database\Factories;

use App\Enums\DiscountRuleType;
use App\Models\DiscountRule;
use App\Models\Pharmacy;
use App\Models\PriceUpload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscountRule>
 */
class DiscountRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'price_upload_id' => PriceUpload::factory(),
            'pharmacy_id' => Pharmacy::factory(),
            'type' => DiscountRuleType::Goods,
            'percent' => 5,
            'min_sum' => null,
            'raw_text' => 'Товарная скидка 5%',
        ];
    }

    /**
     * Indicate that the discount lowers the price of every product on the sheet.
     */
    public function price(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DiscountRuleType::Price,
            'raw_text' => '-5% Скидка!',
        ]);
    }
}
