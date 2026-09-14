<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\AiResearchController;
use App\Http\Controllers\Api\V1\CrmController;
use App\Http\Controllers\Api\V1\GoogleController;
use App\Http\Controllers\Api\V1\GoogleMailboxController;
use App\Http\Controllers\Api\V1\GoogleWorkspaceController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\LinkedInController;
use App\Http\Controllers\Api\V1\WebResearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/google/callback', [GoogleController::class, 'callback']);
    Route::get('/linkedin/callback', [LinkedInController::class, 'callback']);
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/google/connect', [GoogleController::class, 'connect']);
        Route::get('/linkedin/connect', [LinkedInController::class, 'connect']);
        Route::get('/linkedin/profile', [LinkedInController::class, 'profile']);
        Route::get('/linkedin/status', [LinkedInController::class, 'status']);
        Route::delete('/linkedin/disconnect', [LinkedInController::class, 'disconnect']);
        Route::post('/web-research/scrape', [WebResearchController::class, 'scrape']);
        Route::get('/google/status', [GoogleController::class, 'status']);
        Route::delete('/google/disconnect', [GoogleController::class, 'disconnect']);
        Route::get('/google/gmail', [GoogleWorkspaceController::class, 'gmail']);
        Route::get('/google/gmail/{id}', [GoogleWorkspaceController::class, 'message']);
        Route::post('/google/gmail/delete', [GoogleWorkspaceController::class, 'deleteMessages']);
        Route::post('/google/gmail/permanent-delete', [GoogleWorkspaceController::class, 'permanentlyDeleteMessages']);
        Route::get('/google/calendar', [GoogleWorkspaceController::class, 'calendar']);
        Route::post('/google/gmail/send', [GoogleMailboxController::class, 'send']);
        Route::post('/ai/chat', [AiResearchController::class, 'chat']);
        Route::post('/ai/lead-search', [AiResearchController::class, 'search']);
        Route::post('/ai/email-coach', [AiResearchController::class, 'emailCoach']);
        Route::post('/ai/email-draft', [AiResearchController::class, 'emailDraft']);
        Route::get('/dashboard', [CrmController::class, 'dashboard']);
        Route::get('/leads', [CrmController::class, 'leads']);
        Route::post('/leads', [CrmController::class, 'storeLead']);
        Route::patch('/leads/{lead}', [CrmController::class, 'updateLead']);
        Route::post('/leads/{lead}/convert', [CrmController::class, 'convertLead']);
        Route::get('/clients', [CrmController::class, 'clients']);
        Route::post('/clients', [CrmController::class, 'storeClient']);
        Route::get('/contacts', [CrmController::class, 'contacts']);
        Route::post('/contacts', [CrmController::class, 'storeContact']);
        Route::get('/agents', [CrmController::class, 'agents']);
        Route::post('/agents', [CrmController::class, 'storeAgent']);
        Route::get('/shipments', [CrmController::class, 'shipments']);
        Route::post('/shipments', [CrmController::class, 'storeShipment']);
        Route::get('/follow-ups', [CrmController::class, 'followUps']);
        Route::post('/follow-ups', [CrmController::class, 'storeFollowUp']);
        Route::post('/lead-searches', [AiResearchController::class, 'search']);
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::apiResource('users', UserController::class)->except(['show']);
        });
    });
});
