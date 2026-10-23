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

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | similarity_threshold: minimal pg_trgm whole-word similarity of the query
    | to a product name or active ingredient; spaces are also ignored, so
    | «ношпа» finds «Но-шпа».
    | relative_gap: once a close match is found, products scoring more than
    | this below it are left out, so a cheaper look-alike cannot win.
    | skeleton_score: the score of a name whose consonants start with the
    | query's consonants, which catches typos in vowels («анолгин»).
    | suggestion_threshold: how close a name must be to be suggested when
    | the search finds nothing.
    |
    */

    'search' => [
        'similarity_threshold' => (float) env('PRICES_SEARCH_THRESHOLD', 0.4),
        'relative_gap' => 0.15,
        'skeleton_score' => 0.6,
        'offers_per_pack' => 3,
        'suggestions' => 5,
        'suggestion_threshold' => 0.3,
    ],

];
