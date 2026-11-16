<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Notification\IndexRequest;
use App\Http\Resources\NotificationPopoverResource;
use App\UseCases\Notification\Api\IndexAction;
use App\UseCases\Notification\MarkAllAsReadAction;
use App\UseCases\Notification\MarkAsReadAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;

/**
 * 認証ユーザー向けの通知 API Controller。
 *
 * - 通知一覧の取得
 * - 通知の既読化・全通知の一括既読化
 *
 * 通知の閲覧・既読化は NotificationPolicy で認可し、
 * 通知の取得範囲は Action 側で認証ユーザー本人に限定する。
 */
class NotificationController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): AnonymousResourceCollection
    {
        $tab = (string) $request->input('tab', 'all');
        $perPage = (int) $request->input('per_page', 20);

        return NotificationPopoverResource::collection($action($request->user(), $tab, $perPage));
    }

    public function markAsRead(
        Request $request,
        DatabaseNotification $notification,
        MarkAsReadAction $action,
    ): JsonResponse {
        $this->authorize('markAsRead', $notification);

        $notification = $action($request->user(), $notification);

        return response()->json([
            'success' => true,
            'notification_id' => $notification->id,
            'read_at' => $notification->read_at?->toISOString(),
        ]);
    }

    public function markAllAsRead(
        Request $request,
        MarkAllAsReadAction $action,
    ): JsonResponse {
        $this->authorize('markAllAsRead', DatabaseNotification::class);

        $user = $request->user();
        $markedCount = $action($user);

        return response()->json([
            'success' => true,
            'marked_count' => $markedCount,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }
}
