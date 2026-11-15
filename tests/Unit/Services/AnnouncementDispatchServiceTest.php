<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use App\Services\AnnouncementDispatchService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AnnouncementDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_sends_notification_to_all_recipients(): void
    {
        Notification::fake();

        $announcement = Announcement::factory()->create();

        $recipient1 = User::factory()->create();
        $recipient2 = User::factory()->create();
        $recipient3 = User::factory()->create();

        $recipients = new Collection([
            $recipient1,
            $recipient2,
            $recipient3,
        ]);

        $service = app(AnnouncementDispatchService::class);

        // Act
        $service->dispatch($announcement, $recipients);

        // Assert
        Notification::assertSentTo($recipient1, AdminAnnouncementNotification::class);
        Notification::assertSentTo($recipient2, AdminAnnouncementNotification::class);
        Notification::assertSentTo($recipient3, AdminAnnouncementNotification::class);
    }
}
