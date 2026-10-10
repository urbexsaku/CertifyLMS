<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Notification\IndexRequest;
use App\UseCases\Notification\IndexAction;
use App\UseCases\Notification\MarkAsReadAction;
use App\UseCases\Notification\MarkAllAsReadAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
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
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $this->authorize('viewAny', DatabaseNotification::class);

        $user = $request->user();
        $tab = $request->validated('tab');

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

        $action($request->user(), $notification);

        return redirect()->route('notifications.index');
    }

    public function markAllAsRead(Request $request, MarkAllAsReadAction $action): RedirectResponse
    {
        $this->authorize('markAllAsRead', DatabaseNotification::class);

        $action($request->user());

        return redirect()->route('notifications.index');
    }
}
