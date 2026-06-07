<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Middleware\JwtAuthenticate;
use App\Http\Middleware\RoleCheck;
use Illuminate\Support\Facades\Route;

// ── v1 auth endpoints ─────────────────────────────────────────────────────────
Route::prefix('v1/auth')->group(function () {

    // Public — no JWT required
    Route::post('/login',            [AuthController::class, 'login']);
    Route::post('/principal/login',  [AuthController::class, 'login']); // same logic, role enforced in response
    Route::post('/refresh',          [AuthController::class, 'refresh']);

    // Protected — require valid JWT
    Route::middleware(JwtAuthenticate::class)->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });
});

// ── Future v1 routes go here (Phase 4) ───────────────────────────────────────
// Route::prefix('v1')->middleware(JwtAuth::class)->group(function () {
//     Route::apiResource('attendance', AttendanceController::class);
//     Route::apiResource('students',   StudentController::class);
// });
