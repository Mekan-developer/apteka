<?php

namespace Database\Factories;

use App\Models\Pharmacy;
use App\Models\PriceItem;
use App\Models\PriceUpload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceItem>
 */
class PriceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $baseName = fake()->randomElement(['цитрамон', 'аналгин', 'парацетамол', 'амброксол', 'но-шпа']);
        $dose = fake()->randomElement(['200мг', '500мг', null]);
        $packCount = fake()->randomElement([4, 6, 10, 20]);
        $rawName = trim(mb_convert_case($baseName, MB_CASE_TITLE)." таб. {$dose} №{$packCount}");
        $price = fake()->randomFloat(2, 1, 200);

        return [
            'price_upload_id' => PriceUpload::factory(),
            'pharmacy_id' => Pharmacy::factory(),
            'raw_name' => $rawName,
            'name_normalized' => mb_strtolower($rawName),
            'name_skeleton' => preg_replace('/(.)\1+/u', '$1', (string) preg_replace('/[^бвгджзйклмнпрстфхцчшщ]/u', '', $baseName)),
            'base_name' => $baseName,
            'name_compact' => str_replace(' ', '', $baseName),
            'active_ingredient' => null,
            'pack_count' => $packCount,
            'dose' => $dose,
            'price' => $price,
            'price_discounted' => null,
            'effective_price' => $price,
            'goods_discount_percent' => null,
            'discount_note' => null,
            'manufacturer' => fake()->optional()->randomElement(['Индия', 'Беларусь', 'Грузия', 'Пакистан']),
            'source_row' => fake()->numberBetween(5, 1000),
        ];
    }
}
