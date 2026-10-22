<?php

namespace Database\Factories;

use App\Enums\PriceUploadSheetStatus;
use App\Models\Pharmacy;
use App\Models\PriceUpload;
use App\Models\PriceUploadSheet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceUploadSheet>
 */
class PriceUploadSheetFactory extends Factory
{
    /**
     * Sheet indexes must be unique within an upload, so every created sheet gets the next one.
     */
    protected static int $sheetIndex = 0;

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
            'sheet_name' => fake()->company(),
            'sheet_index' => static::$sheetIndex++,
            'status' => PriceUploadSheetStatus::Parsed,
            'header_row' => 4,
            'name_col' => 1,
            'price_col' => 2,
            'price_discounted_col' => null,
            'rows_parsed' => fake()->numberBetween(10, 1000),
            'error' => null,
            'preview_json' => [
                ['ПРАЙС ЛИСТ'],
                ['№', 'Наименование', 'Цена'],
                [1, 'Цитрамон таб. №6', 1.5],
            ],
        ];
    }

    /**
     * Indicate that the columns could not be detected and an admin has to choose them.
     */
    public function needsReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PriceUploadSheetStatus::NeedsReview,
            'header_row' => null,
            'name_col' => null,
            'price_col' => null,
            'rows_parsed' => 0,
        ]);
    }
}
