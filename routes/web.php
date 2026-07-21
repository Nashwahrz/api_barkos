<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return 'connection sucses';
});
  
Route::get('/storage/{path}', function () {  = storage_path('app/public/' . ); if (file_exists()) { return response()-; } abort(404); })-, '.*'); 
