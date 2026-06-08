<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DetentionController;
use App\Http\Controllers\Api\V1\HodController;
use App\Http\Controllers\Api\V1\PrincipalController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Middleware\JwtAuthenticate;
use App\Http\Middleware\RoleCheck;
use Illuminate\Support\Facades\Route;

// ── v1 auth endpoints ─────────────────────────────────────────────────────────
Route::prefix('v1/auth')->middleware('throttle:api')->group(function () {

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

// ── v1 protected endpoints (Phase 4) ──────────────────────────────────────────
Route::prefix('v1')->middleware(['throttle:api', JwtAuthenticate::class])->group(function () {

    // Attendance
    Route::prefix('attendance')->group(function () {
        Route::post('/',                    [AttendanceController::class, 'store'])->middleware(RoleCheck::class . ':teacher,admin');
        Route::get('/students',             [AttendanceController::class, 'students'])->middleware(RoleCheck::class . ':teacher,admin');
        Route::get('/subjects',             [AttendanceController::class, 'subjects'])->middleware(RoleCheck::class . ':teacher,admin');
        Route::get('/monthly/{student}',    [AttendanceController::class, 'monthly']); // any authenticated role
    });

    // Students — admin manages, everyone else can read
    Route::get('/students',           [StudentController::class, 'index']);
    Route::get('/students/{student}', [StudentController::class, 'show']);
    Route::middleware(RoleCheck::class . ':admin')->group(function () {
        Route::post('/students',             [StudentController::class, 'store']);
        Route::post('/students/upload',      [StudentController::class, 'upload']);
        Route::put('/students/{student}',    [StudentController::class, 'update']);
        Route::delete('/students/{student}', [StudentController::class, 'destroy']);
    });

    // Subjects — admin manages, everyone else can read
    Route::get('/subjects',           [SubjectController::class, 'index']);
    Route::get('/subjects/{subject}', [SubjectController::class, 'show']);
    Route::middleware(RoleCheck::class . ':admin')->group(function () {
        Route::post('/subjects',             [SubjectController::class, 'store']);
        Route::post('/subjects/upload',      [SubjectController::class, 'upload']);
        Route::put('/subjects/{subject}',    [SubjectController::class, 'update']);
        Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy']);
    });

    // HOD
    Route::post('/hod/summary', [HodController::class, 'submit'])->middleware(RoleCheck::class . ':hod');

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('/dashboard',           [DashboardController::class, 'summary']); // role-filtered internally
        Route::get('/detention',           [DetentionController::class, 'index'])->middleware(RoleCheck::class . ':hod,principal');
        Route::post('/detention/generate', [DetentionController::class, 'generate'])->middleware(RoleCheck::class . ':principal');
        Route::get('/hod/{department}',    [HodController::class, 'dashboard'])->middleware(RoleCheck::class . ':hod,principal');
        Route::get('/principal',           [PrincipalController::class, 'overview'])->middleware(RoleCheck::class . ':principal');
    });
});
