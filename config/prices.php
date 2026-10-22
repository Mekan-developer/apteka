<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Price Uploads
    |--------------------------------------------------------------------------
    |
    | Weekly Excel price files are kept on this disk as history. The disk must
    | be local: the spreadsheet reader opens the stored file by its path.
    |
    */

    'disk' => env('PRICES_DISK', 'local'),

    'directory' => 'price-uploads',

    'max_upload_kilobytes' => (int) env('PRICES_MAX_UPLOAD_KILOBYTES', 40960),

];
