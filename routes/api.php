<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StylistController;
use App\Http\Controllers\UserController;
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
    
    /**/ 
    Route::post('services',                  [ServiceController::class, 'Save'])->name('services.store');      // crear
    Route::post('services/{service}',       [ServiceController::class, 'update'])->name('services.update');
    Route::put('services/{service}',      [ServiceController::class, 'destroy'])->name('services.destroy'); // desactivar (active=false)
    Route::post('services/{service}/active',[ServiceController::class, 'setActive'])->name('services.active');// activar/desactivar

    /* */
    Route::get('products',                [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{product}',      [ProductController::class, 'show'])->name('products.show');
    Route::post('products',               [ProductController::class, 'store'])->name('products.store');          // crear
    Route::post('products/{product}',     [ProductController::class, 'update'])->name('products.update');        // actualizar (POST)
    Route::post('products/{product}/in-stock', [ProductController::class, 'setInStock'])->name('products.inStock');
    Route::delete('products/{product}',   [ProductController::class, 'destroy'])->name('products.destroy');      // “eliminar” => fuera de stock

    
    /**  */
    Route::get('users/names', [UserController::class, 'names'])->name('users.names');
    Route::get('users/info', [UserController::class, 'info'])->name('users.info');
    

    Route::get('/appointments', [AppointmentController::class, 'index']);

    // Solo citas del usuario logeado
    Route::get('appointments/mine', [AppointmentController::class, 'mine']);
    Route::get('appointments/mine/upcoming', [AppointmentController::class, 'myUpcoming']);
    Route::get('appointments/mine/pending', [AppointmentController::class, 'myPending']);

    // Acciones sobre una cita (aceptar/declinar/cancelar/reagendar)
    Route::post('appointments/{appointment}/accept',  [AppointmentController::class, 'accept']);
    Route::post('appointments/{appointment}/decline', [AppointmentController::class, 'decline']);
    Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::patch('appointments/{appointment}/rebook', [AppointmentController::class, 'rebook']);
});
Route::middleware('auth:sanctum')->get('users', [UserController::class, 'index'])
->name('users.index');

/* email */
Route::post('contact', [ContactController::class, 'send']); 
Route::get('services',            [ServiceController::class, 'index'])->name('services.index');
Route::get('services/{service}',  [ServiceController::class, 'show'])->name('services.show');

/* */
Route::get('catalog/products', [ProductController::class, 'publicIndex'])->name('products.public');

/**/
Route::get('catalog/stylists', [StylistController::class, 'publicIndex'])->name('stylists.public');

/**/
Route::get('stylists',               [StylistController::class, 'index'])->name('stylists.index');
Route::get('stylists/{id}',          [StylistController::class, 'show'])->name('stylists.show');

Route::post('stylists',              [StylistController::class, 'store'])->name('stylists.store');       // crear
Route::post('stylists/{id}',         [StylistController::class, 'update'])->name('stylists.update');     // actualizar
Route::post('stylists/{id}/active',  [StylistController::class, 'setActive'])->name('stylists.active');  // activar/inactivar



/**/
Route::post('/bookings', [BookingController::class, 'store']);
Route::post('/bookings/{booking}/items', [BookingController::class, 'storeItem']); // opcional