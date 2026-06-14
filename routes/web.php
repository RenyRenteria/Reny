<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/videos', function () {
    return view('videos');
});

Route::get('/photos', function () {
    return view('photos');
});

Route::get('/community', function () {
    return view('community');
});

Route::get('/store', function () {
    return view('store');
});
