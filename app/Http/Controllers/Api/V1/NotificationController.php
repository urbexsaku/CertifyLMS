<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationPopoverResource;
use App\UseCases\Notification\IndexAction;
use App\UseCases\Notification\MarkAllAsReadAction;
use App\UseCases\Notification\MarkAsReadAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function index(Request $request, IndexAction $action): JsonResponse
    {
        $this->authorize('viewAny', DatabaseNotification::class);

        $user = $request->user();
        $tab = $request->query('tab', 'all');

        if (! in_array($tab, ['all', 'unread'], true)) {
            $tab = 'all';
        }

        $notifications = $action($user, $tab);

        return response()->json([
            'data' => NotificationPopoverResource::collection(
                $notifications->getCollection()
            )->resolve($request),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
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
