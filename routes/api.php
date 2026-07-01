<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CampaignMapController;
use App\Http\Controllers\Api\CampaignSessionController;
use App\Http\Controllers\Api\CharacterSheetController;
use App\Http\Controllers\Api\SessionMessageController;
use App\Http\Controllers\Api\SheetModelController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::post('/campaigns', [CampaignController::class, 'store']);
    Route::get('/campaigns/{slug}', [CampaignController::class, 'show']);
    Route::put('/campaigns/{slug}', [CampaignController::class, 'update']);
    Route::delete('/campaigns/{slug}', [CampaignController::class, 'destroy']);
    Route::post('/campaigns/{slug}/join', [CampaignController::class, 'join']);
    Route::post('/campaigns/{slug}/leave', [CampaignController::class, 'leave']);
    Route::get('/campaigns/{slug}/members', [CampaignController::class, 'members']);

    Route::get('/sheet-models', [SheetModelController::class, 'index']);
    Route::post('/sheet-models', [SheetModelController::class, 'store']);
    Route::get('/sheet-models/{slug}', [SheetModelController::class, 'show']);
    Route::put('/sheet-models/{slug}', [SheetModelController::class, 'update']);
    Route::delete('/sheet-models/{slug}', [SheetModelController::class, 'destroy']);
    Route::post('/campaigns/{campaignSlug}/sheet-models/{modelSlug}', [SheetModelController::class, 'attachToCampaign']);
    Route::delete('/campaigns/{campaignSlug}/sheet-models/{modelSlug}', [SheetModelController::class, 'detachFromCampaign']);

    Route::get('/campaigns/{campaignSlug}/sheets', [CharacterSheetController::class, 'index']);
    Route::post('/campaigns/{campaignSlug}/sheets', [CharacterSheetController::class, 'store']);
    Route::get('/campaigns/{campaignSlug}/sheets/{sheetId}', [CharacterSheetController::class, 'show']);
    Route::put('/campaigns/{campaignSlug}/sheets/{sheetId}', [CharacterSheetController::class, 'update']);
    Route::delete('/campaigns/{campaignSlug}/sheets/{sheetId}', [CharacterSheetController::class, 'destroy']);

    Route::get('/campaigns/{campaignSlug}/maps', [CampaignMapController::class, 'index']);
    Route::post('/campaigns/{campaignSlug}/maps', [CampaignMapController::class, 'store']);
    Route::put('/campaigns/{campaignSlug}/maps/{mapId}', [CampaignMapController::class, 'update']);
    Route::delete('/campaigns/{campaignSlug}/maps/{mapId}', [CampaignMapController::class, 'destroy']);

    Route::get('/campaigns/{campaignSlug}/sessions', [CampaignSessionController::class, 'index']);
    Route::get('/campaigns/{campaignSlug}/sessions/active', [CampaignSessionController::class, 'active']);
    Route::post('/campaigns/{campaignSlug}/sessions', [CampaignSessionController::class, 'store']);
    Route::get('/campaigns/{campaignSlug}/sessions/{sessionId}', [CampaignSessionController::class, 'show']);
    Route::put('/campaigns/{campaignSlug}/sessions/{sessionId}', [CampaignSessionController::class, 'update']);
    Route::delete('/campaigns/{campaignSlug}/sessions/{sessionId}', [CampaignSessionController::class, 'destroy']);

    Route::get('/campaigns/{campaignSlug}/sessions/{sessionId}/messages', [SessionMessageController::class, 'index']);
    Route::post('/campaigns/{campaignSlug}/sessions/{sessionId}/messages', [SessionMessageController::class, 'store']);
});
