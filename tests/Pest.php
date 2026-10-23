<?php

use App\Models\Pharmacy;
use App\Models\PriceItem;
use App\Models\PriceUpload;
use App\Services\Prices\Parsing\NameNormalizer;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Rows of a real price sheet from tests/fixtures/prices/sheets.php, keyed by Excel row number.
 *
 * @return array<int, list<mixed>>
 */
function priceSheetRows(string $sheetName): array
{
    static $sheets = null;

    $sheets ??= require __DIR__.'/fixtures/prices/sheets.php';

    return $sheets[$sheetName];
}

/**
 * A product of a pharmacy's sheet, normalized the way the import normalizes it.
 *
 * @param  array<string, mixed>  $attributes
 */
function offer(PriceUpload $upload, string $pharmacy, string $name, float $price, array $attributes = []): PriceItem
{
    $normalized = (new NameNormalizer)->normalize($name);

    return PriceItem::factory()
        ->for($upload)
        ->for(Pharmacy::query()->firstOrCreate(['name' => $pharmacy]))
        ->create([
            'raw_name' => $name,
            'name_normalized' => $normalized->normalized,
            'name_skeleton' => $normalized->skeleton,
            'base_name' => $normalized->baseName,
            'name_compact' => str_replace(' ', '', $normalized->baseName),
            'active_ingredient' => $normalized->activeIngredient,
            'pack_count' => $normalized->packCount,
            'dose' => $normalized->dose,
            'price' => $price,
            'price_discounted' => null,
            'effective_price' => $price,
            'goods_discount_percent' => null,
            ...$attributes,
        ]);
}

/**
 * An upload whose stored file holds the given sheets, on a faked local disk.
 *
 * @param  array<string, list<list<mixed>>>  $sheets  Sheet name => rows.
 */
function uploadWithSheets(array $sheets): PriceUpload
{
    Storage::fake('local');
    Storage::disk('local')->put('price-uploads/prices.xlsx', (string) file_get_contents(xlsxFile($sheets)));

    return PriceUpload::factory()->create(['stored_path' => 'price-uploads/prices.xlsx']);
}

/**
 * Write an .xlsx file with the given sheets to a temporary path that is removed when the run ends.
 *
 * Pass a Cell instead of a plain value when the cell needs a style, e.g. a date format.
 *
 * @param  array<string, list<list<bool|Cell|DateTimeInterface|float|int|string|null>>>  $sheets  Sheet name => rows.
 */
function xlsxFile(array $sheets): string
{
    $directory = sys_get_temp_dir().'/apteka-tests-'.getmypid();

    if (! is_dir($directory)) {
        mkdir($directory);
        register_shutdown_function(fn () => (new Filesystem)->deleteDirectory($directory));
    }

    $path = $directory.'/'.uniqid('prices-', true).'.xlsx';

    $writer = new Writer;
    $writer->openToFile($path);

    foreach (array_keys($sheets) as $position => $name) {
        $sheet = $position === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName($name);

        foreach ($sheets[$name] as $values) {
            $writer->addRow(new Row(array_map(
                fn (bool|Cell|DateTimeInterface|float|int|string|null $value): Cell => $value instanceof Cell ? $value : Cell::fromValue($value),
                $values,
            )));
        }
    }

    $writer->close();

    return $path;
}
