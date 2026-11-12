<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| JSON API のルート定義。
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// ============================================================
// 通知 API (Sanctum Cookie 認証)
// ============================================================
Route::middleware('auth:sanctum')
    ->prefix('v1/notifications')
    ->name('api.v1.notifications.')
    ->group(function () {
        Route::get('/', [NotificationController::class, 'index'])
            ->name('index');

        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])
            ->name('read');

        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])
            ->name('readAll');
    });
