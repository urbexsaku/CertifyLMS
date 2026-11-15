<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use App\UseCases\Notification\SendNotificationAction;
use Illuminate\Database\Eloquent\Collection;

/**
 * お知らせ通知を対象受講生に配信するサービス。
 */
final class AnnouncementDispatchService
{
    public function __construct(private readonly SendNotificationAction $sendNotificationAction) {}

    /**
     * 対象受講生にお知らせ通知を配信する。
     *
     * @param Collection<int, User> $recipients
     */
    public function dispatch(Announcement $announcement, Collection $recipients): void
    {
        foreach ($recipients as $recipient) {
            $this->sendNotificationAction->__invoke($recipient, new AdminAnnouncementNotification($announcement));
        }
    }
}
