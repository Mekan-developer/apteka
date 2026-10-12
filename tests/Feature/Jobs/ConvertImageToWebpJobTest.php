<?php

use App\Jobs\ConvertImageToWebpJob;
use Illuminate\Support\Facades\Storage;

test('a job that finally fails removes the temporary original', function () {
    Storage::fake('local');
    Storage::disk('local')->put('tmp/images/broken.jpg', 'not an image');

    (new ConvertImageToWebpJob('tmp/images/broken.jpg', 'avatars/broken.webp'))
        ->failed(new RuntimeException('Unable to decode image.'));

    Storage::disk('local')->assertMissing('tmp/images/broken.jpg');
});
