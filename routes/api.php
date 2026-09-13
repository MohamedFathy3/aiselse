<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|
| All routes are versioned under /api/v1 so breaking changes in later
| phases (leads, clients, shipments, gmail, calendar, ai...) don't affect
| already-shipped frontend builds. Every route group below re-declares its
| own authorization; the frontend route/menu visibility is NEVER trusted
| as the source of truth (see spec section 3).
|
*/

Route::prefix('v1')->group(function () {

    // --- Public auth endpoints (rate-limited via LoginRequest + Laravel's default throttle) ---
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    // --- Authenticated endpoints (Sanctum SPA cookie session) ---
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Placeholder until Phase 8 (Dashboards & Analytics) - confirms the
        // authenticated-request pipeline works end to end for Phase 1.
        Route::get('/dashboard', function (Request $request) {
            return response()->json([
                'message' => 'Authenticated as '.$request->user()->name,
                'role' => $request->user()->role->value,
            ]);
        });

        // Leads, clients, contacts, agents, shipments, follow-ups, activities,
        // emails, calendar, ai, lead-searches routes are added in Phases 2-7.

        // --- Admin-only endpoints ---
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::apiResource('users', UserController::class)->except(['show']);
        });
    });
});
