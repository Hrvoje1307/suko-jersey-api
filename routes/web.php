<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| API dokumentacija (Swagger UI)
|--------------------------------------------------------------------------
|
| Spec je public/openapi.yaml — ručno održavan source of truth koji se servira
| statički. Swagger UI ga učitava i nudi "Try it out" protiv trenutnog hosta.
|
*/

Route::get('/docs', function () {
    return view('docs', ['specUrl' => url('openapi.yaml')]);
})->name('docs');
