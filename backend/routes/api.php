<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\StaffController;
use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/hello', function () {
    DB::select('SELECT 1');
    return response()->json(['message' => 'Hello from PAWADOPT! Laravel API and database are connected.', 'database' => 'connected', 'version' => config('pawadopt.version')]);
});
Route::get('/pets', [PetController::class, 'index']);
Route::get('/pets/{pet}', fn (Pet $pet) => response()->json(['data' => $pet]));
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/contact', [PetController::class, 'contact'])->middleware('throttle:5,1');

Route::middleware('paw.auth')->group(function () {
    Route::get('/me', fn (Request $request) => response()->json(['user' => $request->user()]));
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/account/password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1');
    Route::get('/favorites', [PetController::class, 'favorites']);
    Route::put('/favorites/{pet}', [PetController::class, 'saveFavorite']);
    Route::delete('/favorites/{pet}', [PetController::class, 'removeFavorite']);
    Route::get('/applications', [PetController::class, 'applications']);
    Route::post('/pets/{pet}/applications', [PetController::class, 'apply'])->middleware('throttle:10,1');
    Route::prefix('staff')->middleware('paw.role:admin,manager')->group(function () {
        Route::get('/overview', [StaffController::class, 'overview']);
        Route::get('/applications', [StaffController::class, 'applications']);
        Route::patch('/applications/{application}', [StaffController::class, 'review']);
        Route::post('/pets', [StaffController::class, 'savePet']);
        Route::put('/pets/{pet}', [StaffController::class, 'savePet']);
        Route::get('/messages', [StaffController::class, 'messages']);
        Route::patch('/messages/{message}', [StaffController::class, 'resolveMessage']);
        Route::middleware('paw.role:admin')->group(function () {
            Route::get('/users', [StaffController::class, 'users']);
            Route::patch('/users/{user}/role', [StaffController::class, 'changeRole']);
        });
    });
});
