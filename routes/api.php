<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login',    [AuthController::class, 'login']);
Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']); // enviar email
Route::post('auth/reset-password',  [AuthController::class, 'resetPassword']);  // aplicar nueva contraseña

// Rutas protegidas por token:
Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me',   [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    /*Boking/*/
    Route::get('bookings/me', [BookingController::class, 'getMyBookings']);
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel']);




});

/* email */
Route::post('contact', [ContactController::class, 'send']); 
Route::post('services', [ServiceController::class, 'store']);
