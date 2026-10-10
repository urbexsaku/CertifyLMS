<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Notificationに対する認可ポリシー。
 *
 * - viewAny: 認証ユーザーは通知一覧を閲覧可。
 * - markAsRead: 自分宛ての通知のみ既読化可。
 * - markAllAsRead: 自分宛ての通知を一括既読化可。
 *
 * 通知の取得範囲はAction側で絞る。
 */
class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function markAsRead(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass()
            && $notification->notifiable_id === $user->getKey();
    }

    public function markAllAsRead(User $user): bool
    {
        return true;
    }
}
