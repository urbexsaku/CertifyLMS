<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\UseCases\Notification\IndexAction;
use App\UseCases\Notification\MarkAllAsReadAction;
use App\UseCases\Notification\MarkAsReadAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\View\View;

/**
 * 通知基盤 Controller。 student / coach 共通の閲覧導線(index / markAsRead / markAllAsRead)を提供する。
 *
 * - student: 自分宛ての通知一覧 / 通知の既読化 / 全通知の既読化
 * - coach: 自分宛ての通知一覧 / 通知の既読化 / 全通知の既読化
 *
 * 表示要素のロール差は Blade の `@can` / `auth()->user()->role` 判定で出し分ける。
 */
class NotificationController extends Controller
{
    public function index(Request $request, IndexAction $action): View
    {
        $this->authorize('viewAny', DatabaseNotification::class);

        $user = $request->user();
        $tab = $request->query('tab', 'all');

        if (! in_array($tab, ['all', 'unread'], true)) {
            $tab = 'all';
        }

        $notifications = $action($user, $tab);
        $unreadCount = $user->unreadNotifications()->count();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'tab' => $tab,
        ]);
    }

    public function markAsRead(Request $request, DatabaseNotification $notification, MarkAsReadAction $action): RedirectResponse
    {
        $this->authorize('markAsRead', $notification);

        $notification = $action($request->user(), $notification);

        return redirect()->to(
            $this->resolveNotificationUrl($request, $notification)
        );
    }

    public function markAllAsRead(Request $request, MarkAllAsReadAction $action): RedirectResponse
    {
        $this->authorize('markAllAsRead', DatabaseNotification::class);

        $action($request->user());

        return redirect()->route('notifications.index');
    }

    private function resolveNotificationUrl(Request $request, DatabaseNotification $notification): string
    {
        $data = is_array($notification->data)
            ? $notification->data
            : [];

        $type = $data['notification_type'] ?? null;
        $params = $data['route_params'] ?? null;

        try {
            return match ($type) {
                'chat_message_received' => $this->resolveParameterizedRoute(
                    'chat.show',
                    $params,
                    'room'
                ),

                'qa_reply_received' => $this->resolveParameterizedRoute(
                    'qa-board.show',
                    $params,
                    'thread'
                ),

                'meeting_reserved',
                'meeting_canceled' => route(
                    $request->user()->role === UserRole::Coach
                        ? 'coach.meetings.index'
                        : 'meetings.index'
                ),

                default => route('notifications.index'),
            };
        } catch (UrlGenerationException $e) {
            return route('notifications.index');
        }
    }

    private function resolveParameterizedRoute(string $routeName, mixed $params, string $requiredParam): string
    {
        if (
            ! is_array($params)
            || ! isset($params[$requiredParam])
            || ! is_scalar($params[$requiredParam])
            || $params[$requiredParam] === ''
        ) {
            return route('notifications.index');
        }

        return route($routeName, $params);
    }
}
