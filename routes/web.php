<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return 'connection sucses';
});
  
Route::get('/storage/{path}', function ($path) {
    $file = storage_path('app/public/' . $path);
    if (file_exists($file)) {
        return response()->file($file);
    }
    abort(404);
})->where('path', '.*');
