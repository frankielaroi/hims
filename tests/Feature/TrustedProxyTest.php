<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_proxy-probe', fn (Request $request) => [
        'secure' => $request->isSecure(),
        'ip' => $request->ip(),
        'url' => url('/dashboard'),
    ]);
});

test('forwarded headers from the app service front end are trusted', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '169.254.130.1'])
        ->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '41.66.200.10',
            'X-Forwarded-Host' => 'hims.azurewebsites.net',
            'X-Forwarded-Port' => '443',
        ])
        ->get('/_proxy-probe')
        ->assertOk()
        ->assertJson([
            'secure' => true,
            'ip' => '41.66.200.10',
            'url' => 'https://hims.azurewebsites.net/dashboard',
        ]);
});

test('requests without forwarded headers are unaffected', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->get('/_proxy-probe')
        ->assertOk()
        ->assertJson([
            'secure' => false,
            'ip' => '10.0.0.5',
        ]);
});
