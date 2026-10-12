<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Image Storage
    |--------------------------------------------------------------------------
    |
    | Every uploaded image is stored as WebP only. The original upload is kept
    | on the temporary disk until ConvertImageToWebpJob converts it, and is
    | then deleted.
    |
    */

    'disk' => env('IMAGES_DISK', 'public'),

    'temporary_disk' => env('IMAGES_TEMPORARY_DISK', 'local'),

    'temporary_directory' => 'tmp/images',

    'webp_quality' => (int) env('IMAGES_WEBP_QUALITY', 80),

];
