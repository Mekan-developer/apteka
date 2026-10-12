<?php

/**
 * Flatten nested translation arrays into dot-notation keys.
 *
 * @param  array<string, mixed>  $lines
 * @return list<string>
 */
function translationKeys(array $lines, string $prefix = ''): array
{
    $keys = [];

    foreach ($lines as $key => $value) {
        $keys = is_array($value) && $value !== []
            ? [...$keys, ...translationKeys($value, "{$prefix}{$key}.")]
            : [...$keys, "{$prefix}{$key}"];
    }

    sort($keys);

    return $keys;
}

test('ru and tk define the same keys in every php translation file', function (string $file) {
    $ruKeys = translationKeys(require lang_path("ru/{$file}"));
    $tkKeys = translationKeys(require lang_path("tk/{$file}"));

    expect($tkKeys)->toBe($ruKeys);
})->with(array_map('basename', glob(dirname(__DIR__, 2).'/lang/ru/*.php')));

test('every ru php translation file has a tk counterpart', function () {
    $ruFiles = array_map('basename', glob(lang_path('ru/*.php')));
    $tkFiles = array_map('basename', glob(lang_path('tk/*.php')));

    expect($tkFiles)->toBe($ruFiles);
});

test('ru.json and tk.json define the same keys', function () {
    $ruKeys = translationKeys(json_decode(file_get_contents(lang_path('ru.json')), true, flags: JSON_THROW_ON_ERROR));
    $tkKeys = translationKeys(json_decode(file_get_contents(lang_path('tk.json')), true, flags: JSON_THROW_ON_ERROR));

    expect($tkKeys)->toBe($ruKeys);
});
