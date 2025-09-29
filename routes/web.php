<?php

use Illuminate\Support\Facades\Route;
use App\Services\InstagramService;
use App\Models\Post;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/test-instagram', function (InstagramService $service) {
    return $service->testPostPhoto();
});
