<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dermanhanalaryň baha sanawlary
    |--------------------------------------------------------------------------
    |
    | Açarlar lang/ru/prices.php bilen gabat gelmeli (test barlaýar).
    |
    */

    'file_not_found' => '«:path» faýly tapylmady.',
    'cannot_read' => 'Faýly okap bolmady: :error',

    'inspect' => [
        'sheet' => ':index-nji sahypa «:name»: boş däl setirler — :rows',
        'empty_sheet' => 'Sahypa boş.',
        'summary' => 'Sahypalar: :sheets, boş däl setirler: :rows, wagt: :seconds sek, iň ýokary ýat: :memory MB.',
    ],

    'dry_run' => [
        'headers' => [
            'sheet' => 'Sahypa',
            'status' => 'Ýagdaýy',
            'header_row' => 'Sözbaşy',
            'name_col' => 'Ady',
            'price_col' => 'Bahasy',
            'discounted_col' => 'Arzanladylan bahasy',
            'items' => 'Harytlar',
            'discounts' => 'Sahypanyň arzanladyşlary',
            'reason' => 'Sebäbi',
        ],
        'item_headers' => [
            'row' => 'Setir',
            'raw_name' => 'Sanawdaky ady',
            'base_name' => 'Adyň esasy',
            'pack' => '№',
            'dose' => 'Möçberi',
            'price' => 'Bahasy',
            'effective_price' => 'Gözleg üçin baha',
            'goods_discount' => 'Haryt arzanladyşy',
        ],
        'no_header' => 'ýok',
        'sheet_not_found' => '«:name» sahypasy tapylmady.',
        'summary' => 'Sahypalar: :sheets (seljerildi: :parsed, barlag üçin: :review, geçildi: :skipped), harytlar: :items, wagt: :seconds sek, iň ýokary ýat: :memory MB.',
    ],

    'sheet_statuses' => [
        'parsed' => 'seljerildi',
        'needs_review' => 'barlag üçin',
        'skipped' => 'geçildi',
        'failed' => 'ýalňyşlyk',
    ],

    'review_reasons' => [
        'columns_not_detected' => 'adyň we bahanyň sütünleri tapylmady',
        'no_products' => 'bahasy bolan bir setir hem ýok',
        'few_prices' => 'setirleriň köpüsinde baha tanalmady',
    ],

    'discounts' => [
        'goods' => 'haryt :percent%',
        'price' => 'baha :percent%',
        'from_sum' => ':sum manatdan :discount',
        'conditional' => ':discount (şertli)',
    ],

    'refresh' => [
        'queued' => 'Täzelemek üçin nobata goýlan ýüklemeler: :count.',
    ],

    'dosage_forms' => [
        'tablets' => 'tabletkalar',
        'capsules' => 'kapsulalar',
        'syrup' => 'sirop',
        'suspension' => 'suspenziýa',
        'injection' => 'sanjymlar',
        'drops' => 'damjalar',
        'spray' => 'sprey',
        'suppositories' => 'şemler',
        'ointment' => 'melhem, gel, krem',
        'powder' => 'poroşok',
        'solution' => 'ergin',
    ],

    // Import edýän wagtymyz kadalaşdyrylan doza birlikleri («мг», «мл», …) we olaryň ulanyja görkezilişi.
    'units' => [
        'мкг' => 'mkg',
        'мг' => 'mg',
        'мл' => 'ml',
        'г' => 'g',
        'ме' => 'HB',
        'ед' => 'birlik',
        '%' => '%',
    ],

];
