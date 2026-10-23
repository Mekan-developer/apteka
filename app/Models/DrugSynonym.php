<?php

namespace App\Models;

use Database\Factories\DrugSynonymFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Two names of one drug, e.g. «аспирин» and «ацетилсалициловая кислота»: searching either finds both.
 * The normalized columns hold the base names the search compares, built by NameNormalizer.
 *
 * @property int $id
 * @property string $term
 * @property string $synonym
 * @property string $term_normalized
 * @property string $synonym_normalized
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['term', 'synonym', 'term_normalized', 'synonym_normalized'])]
class DrugSynonym extends Model
{
    /** @use HasFactory<DrugSynonymFactory> */
    use HasFactory;
}
