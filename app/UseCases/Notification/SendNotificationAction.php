<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * 通知を発行するユースケース。
 */
final class SendNotificationAction
{
    public function __invoke(User $recipient, Notification $notification): void
    {
        if (! in_array($recipient->role, [UserRole::Student, UserRole::Coach], true)) {
            return;
        }

        if ($recipient->status !== UserStatus::InProgress) {
            return;
        }

        $recipient->notify($notification);
    }
}
