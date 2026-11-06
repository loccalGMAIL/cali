<?php

use Illuminate\Support\Facades\Route;

// Landing pública. Cualquier otro host que no sea landing ni panel devuelve 404.
Route::domain(config('domains.landing'))->group(function () {
    Route::view('/', 'landing')->name('landing');
});
