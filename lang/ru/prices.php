<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Прайсы аптек
    |--------------------------------------------------------------------------
    |
    | Ключи должны совпадать с lang/tk/prices.php (это проверяет тест).
    |
    */

    'file_not_found' => 'Файл «:path» не найден.',
    'cannot_read' => 'Не удалось прочитать файл: :error',

    'inspect' => [
        'sheet' => 'Лист :index «:name»: непустых строк — :rows',
        'empty_sheet' => 'Лист пустой.',
        'summary' => 'Листов: :sheets, непустых строк: :rows, время: :seconds с, пик памяти: :memory МБ.',
    ],

    'dry_run' => [
        'headers' => [
            'sheet' => 'Лист',
            'status' => 'Статус',
            'header_row' => 'Заголовок',
            'name_col' => 'Название',
            'price_col' => 'Цена',
            'discounted_col' => 'Цена со скидкой',
            'items' => 'Товаров',
            'discounts' => 'Скидки листа',
            'reason' => 'Причина',
        ],
        'item_headers' => [
            'row' => 'Строка',
            'raw_name' => 'Название в прайсе',
            'base_name' => 'Основа названия',
            'pack' => '№',
            'dose' => 'Доза',
            'price' => 'Цена',
            'effective_price' => 'Цена для поиска',
            'goods_discount' => 'Товарная скидка',
        ],
        'no_header' => 'нет',
        'sheet_not_found' => 'Лист «:name» не найден.',
        'summary' => 'Листов: :sheets (разобрано: :parsed, на проверку: :review, пропущено: :skipped), товаров: :items, время: :seconds с, пик памяти: :memory МБ.',
    ],

    'sheet_statuses' => [
        'parsed' => 'разобран',
        'needs_review' => 'на проверку',
        'skipped' => 'пропущен',
        'failed' => 'ошибка',
    ],

    'review_reasons' => [
        'columns_not_detected' => 'не найдены колонки названия и цены',
        'no_products' => 'нет ни одной строки с ценой',
        'few_prices' => 'у большинства строк цена не распознана',
    ],

    'discounts' => [
        'goods' => 'товарная :percent%',
        'price' => 'ценовая :percent%',
        'from_sum' => ':discount от :sum ман',
        'conditional' => ':discount с условием',
    ],

];
