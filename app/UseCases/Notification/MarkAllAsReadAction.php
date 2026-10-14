<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;

/**
 * 自分宛ての未読通知を一括既読化するユースケース。
 */
final class MarkAllAsReadAction
{
    public function __invoke(User $user): int
    {
        return $user->unreadNotifications()
            ->update(['read_at' => now()]);
    }
}
