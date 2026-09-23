<?php

test('the root url sends visitors to the login page', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
