<?php

namespace Database\Factories;

use App\Enums\PriceUploadStatus;
use App\Models\PriceUpload;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PriceUpload>
 */
class PriceUploadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_filename' => 'prices-'.fake()->date('d.m.Y').'.xlsx',
            'stored_path' => 'price-uploads/'.Str::uuid().'.xlsx',
            'status' => PriceUploadStatus::Uploaded,
            'sheets_total' => 0,
            'sheets_ok' => 0,
            'rows_total' => 0,
            'activated_at' => null,
        ];
    }

    /**
     * Indicate that the upload is the one search currently uses.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PriceUploadStatus::Active,
            'sheets_total' => 98,
            'sheets_ok' => 98,
            'rows_total' => 21844,
            'activated_at' => now(),
        ]);
    }

    /**
     * Indicate that the upload was replaced by a newer one and is kept as history.
     */
    public function archived(): static
    {
        return $this->active()->state(fn (array $attributes) => [
            'status' => PriceUploadStatus::Archived,
        ]);
    }
}
