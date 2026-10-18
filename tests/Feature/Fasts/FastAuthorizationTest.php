<?php

it('refuses guests', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/fasts'],
    ['POST', '/api/fasts'],
    ['GET', '/api/fasts/active'],
    ['PATCH', '/api/fasts/active'],
    ['POST', '/api/fasts/active/end'],
]);
