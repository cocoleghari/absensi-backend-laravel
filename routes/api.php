<?php

use App\Http\Controllers\AdminAbsensiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LokasiController;
use App\Http\Controllers\UserLokasiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

/*
|--------------------------------------------------------------------------
| Public Routes (Tidak perlu login)
|--------------------------------------------------------------------------
*/
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
/*

/*
|--------------------------------------------------------------------------
| Protected Routes (Semua role)
|--------------------------------------------------------------------------
*/
    Route::middleware('auth:sanctum')->group(function () {
        // Logout
        Route::post('/logout', [AuthController::class, 'logout']);

        // Profile user yang sedang login
        Route::get('/user/profile', function (Request $request) {
            return $request->user();
        });

    // User Routes
    Route::middleware('role:user')->group(function () {
        // Lokasi untuk user
        Route::get('/user/lokasi', [UserLokasiController::class, 'getUserLokasi']);

        // Absensi untuk user
        Route::post('/user/absensi/masuk', [UserLokasiController::class, 'submitAbsenMasuk']);
        Route::post('/user/absensi/pulang', [UserLokasiController::class, 'submitAbsenPulang']);

        // Riwayat dan status absensi
        Route::get('/user/absensi/riwayat', [UserLokasiController::class, 'getRiwayatAbsensi']);
        Route::get('/user/absensi/cek-status', [UserLokasiController::class, 'cekStatusHariIni']);
    });

    // Admin Routes
    Route::middleware('auth:sanctum', 'role:admin')->prefix('admin')->group(function () {
        Route::get('/users', [AuthController::class, 'getUser']);
        Route::delete('/users/{id}', [AuthController::class, 'deleteUser']);

        // Absensi untuk admin
        Route::get('/absensi/all', [AdminAbsensiController::class, 'getAllAbsensi']);
        Route::get('/user/all', [AdminAbsensiController::class, 'getAllUsers']);
        Route::delete('/absensi/{id}', [AdminAbsensiController::class, 'deleteAbsensi']);
        Route::get('/absensi/statistics', [AdminAbsensiController::class, 'getStatistics']);
    });

    // Lokasi Routes (Admin dan User)
    Route::middleware('auth:sanctum', 'role:admin,user')->group(function () {
        Route::get('/lokasi', [LokasiController::class, 'index']);
        Route::post('/lokasi', [LokasiController::class, 'store']);
        Route::put('/lokasi/{id}', [LokasiController::class, 'update']);
        Route::delete('/lokasi/{id}', [LokasiController::class, 'destroy']);
        Route::get('/lokasi/users', [LokasiController::class, 'users']);
    });

});
