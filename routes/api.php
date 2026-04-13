<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ChatController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/data', [ChatController::class, 'index'])->middleware('auth:sanctum');

Route::get('/get-data/{id}', [ChatController::class, 'data']);