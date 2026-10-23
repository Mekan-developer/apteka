<?php

namespace Database\Factories;

use App\Models\DrugSynonym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DrugSynonym>
 */
class DrugSynonymFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'term' => 'Аспирин',
            'synonym' => 'Ацетилсалициловая кислота',
            'term_normalized' => 'аспирин',
            'synonym_normalized' => 'ацетилсалициловая кислота',
        ];
    }
}
