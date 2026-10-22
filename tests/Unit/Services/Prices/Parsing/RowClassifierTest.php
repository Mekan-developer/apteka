<?php

use App\Services\Prices\Parsing\ColumnMapping;
use App\Services\Prices\Parsing\RowClassifier;
use App\Services\Prices\Parsing\RowType;

test('tells a product row from other rows', function (array $row, RowType $type) {
    $mapping = new ColumnMapping(headerRow: 2, lastHeaderRow: 2, nameCol: 1, priceCol: 3);

    expect((new RowClassifier)->classify($row, $mapping))->toBe($type);
})->with([
    'product' => [['', 'Бруфен Рапид 200мг капс №10 (Ибупрофен)', 84, 24.99], RowType::Product],
    'product with a price as text' => [['', 'Бетасерк 16мг таб №30', '', '96,00'], RowType::Product],
    'manufacturer group' => [['', '    ABBOTT'], RowType::Text],
    'country separator' => [['', 'Индия', '', ''], RowType::Text],
    'out of stock' => [[14, 'Амоксиклав таб покр п/о 875мг', '', 'ожидаем'], RowType::Text],
    'expiry date in the price column' => [['', 'Средство Педикулицидное "Мирролла" 100мл ,', '', '15.12.2027'], RowType::Text],
    'row number instead of a name' => [[1, 2, 3, 4], RowType::Text],
]);
