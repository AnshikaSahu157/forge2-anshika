<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->get('/user', function (Request $request) {
    return $request->user();
});
