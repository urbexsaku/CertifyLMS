<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * 通知を既読するユースケース。
 */
final class MarkAsReadAction
{
    public function __invoke(User $user, DatabaseNotification $notification): DatabaseNotification
    {
        $notification = $user->notifications()
            ->findOrFail($notification->id);

        $notification->markAsRead();

        return $notification->fresh();
    }
}
