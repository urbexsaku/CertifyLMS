<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Notificationに対する認可ポリシー。
 *
 * - viewAny: coach / student は一覧自体を閲覧可。
 * - markAsRead: coach / student は自分宛ての通知のみ
 * - markAllAsRead: coach / student は一括既読化可
 *
 * 通知の取得範囲はAction側で絞る。
 */
class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Coach, UserRole::Student], true);
    }

    public function markAsRead(User $user, DatabaseNotification $notification): bool
    {
        return in_array($user->role, [UserRole::Coach, UserRole::Student], true)
            && $notification->notifiable_type === $user->getMorphClass()
            && $notification->notifiable_id === $user->getKey();
    }

    public function markAllAsRead(User $user): bool
    {
        return in_array($user->role, [UserRole::Coach, UserRole::Student], true);
    }
}
