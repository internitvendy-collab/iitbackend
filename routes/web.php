<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'IIT Backend API is Running',
    ]);
});

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth')->group(function () {
    Route::get('/session', [AuthController::class, 'session']);
    Route::post('/logout', [AuthController::class, 'logout']);
});