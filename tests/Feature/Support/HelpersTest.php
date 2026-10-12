<?php

test('notify flashes the notification for the next request', function () {
    notify('error', 'Не удалось сохранить.');

    expect(session('notify'))->toBe(['type' => 'error', 'message' => 'Не удалось сохранить.']);
});

test('notify rejects an unknown notification type', function () {
    notify('danger', 'Не удалось сохранить.');
})->throws(InvalidArgumentException::class, 'Unknown notification type [danger].');
