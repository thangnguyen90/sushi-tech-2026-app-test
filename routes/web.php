<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->view('app')
        ->header('Cache-Control', 'cache, public,  must-revalidate, max-age=3600');
});

Route::view('/{any}', 'app', headers: [
    'Cache-Control' => 'cache, public,  must-revalidate, max-age=3600',
])->where('any', '.*');
