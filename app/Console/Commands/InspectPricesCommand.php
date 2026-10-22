<?php

namespace App\Console\Commands;

use App\Services\Prices\PriceSpreadsheetReader;
use App\Services\Prices\SheetPreview;
use DateInterval;
use DateTimeInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use OpenSpout\Common\Exception\OpenSpoutException;

#[Signature('prices:inspect {path : Path to the .xlsx price file} {--rows=8 : How many non-empty rows to show for each sheet}')]
#[Description('List the sheets of a price file with their row counts and first non-empty rows')]
class InspectPricesCommand extends Command
{
    private const int CELL_WIDTH = 40;

    /**
     * Execute the console command.
     */
    public function handle(PriceSpreadsheetReader $reader): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error(__('prices.inspect.file_not_found', ['path' => $path]));

            return self::FAILURE;
        }

        $startedAt = hrtime(true);
        $sheetCount = 0;
        $rowCount = 0;

        try {
            foreach ($reader->previewSheets($path, max(1, (int) $this->option('rows'))) as $sheet) {
                $sheetCount++;
                $rowCount += $sheet->rowCount;

                $this->renderSheet($sheet);
            }
        } catch (OpenSpoutException $exception) {
            $this->error(__('prices.inspect.cannot_read', ['error' => $exception->getMessage()]));

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(__('prices.inspect.summary', [
            'sheets' => $sheetCount,
            'rows' => $rowCount,
            'seconds' => number_format((hrtime(true) - $startedAt) / 1e9, 2),
            'memory' => number_format(memory_get_peak_usage(true) / 1024 / 1024, 1),
        ]));

        return self::SUCCESS;
    }

    private function renderSheet(SheetPreview $sheet): void
    {
        $this->newLine();
        $this->line(__('prices.inspect.sheet', [
            'index' => $sheet->index + 1,
            'name' => $sheet->name,
            'rows' => $sheet->rowCount,
        ]));

        if ($sheet->rows === []) {
            $this->line(__('prices.inspect.empty_sheet'));

            return;
        }

        $width = max(array_map(count(...), $sheet->rows));

        $this->table(
            array_map($this->columnName(...), range(0, $width - 1)),
            array_map(
                fn (array $row): array => array_pad(array_map($this->formatCell(...), $row), $width, ''),
                $sheet->rows,
            ),
        );
    }

    /**
     * Excel column name for a zero-based index: 0 → A, 25 → Z, 26 → AA.
     */
    private function columnName(int $index): string
    {
        $name = '';

        for ($number = $index + 1; $number > 0; $number = intdiv($number - 1, 26)) {
            $name = chr(ord('A') + ($number - 1) % 26).$name;
        }

        return $name;
    }

    /**
     * Show the value as it is stored: floats keep every digit, so "1.1000000000000001" stays visible.
     */
    private function formatCell(bool|DateInterval|DateTimeInterface|float|int|string|null $value): string
    {
        $text = match (true) {
            $value === null => '',
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof DateInterval => $value->format('%H:%I:%S'),
            is_bool($value) => $value ? 'TRUE' : 'FALSE',
            is_float($value) => var_export($value, true),
            default => (string) $value,
        };

        return Str::limit((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text), self::CELL_WIDTH);
    }
}
