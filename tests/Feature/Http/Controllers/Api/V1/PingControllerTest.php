<?php

test('api v1 answers under the /api/v1 prefix with the data and message envelope', function () {
    $response = $this->getJson('/api/v1/ping');

    $response
        ->assertOk()
        ->assertJsonPath('data.version', 'v1')
        ->assertJsonPath('message', 'API v1 работает.')
        ->assertJsonStructure(['data' => ['version', 'time'], 'message']);
});
