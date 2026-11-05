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
        // Arrange
        Notification::fake();

        $service = app(AnnouncementDispatchService::class);

        $recipient1 = User::factory()->student()->inProgress()->create();
        $recipient2 = User::factory()->student()->inProgress()->create();
        $recipient3 = User::factory()->student()->inProgress()->create();
        $recipients = new Collection([$recipient1, $recipient2, $recipient3]);

        $announcement = Announcement::factory()->create();

        // Act
        $dispatchedCount = $service->dispatch($announcement, $recipients);

        // Assert
        $this->assertSame(3, $dispatchedCount);
        $this->assertSame(3, $announcement->fresh()->dispatched_count);

        Notification::assertSentTo($recipient1, AdminAnnouncementNotification::class);
        Notification::assertSentTo($recipient2, AdminAnnouncementNotification::class);
        Notification::assertSentTo($recipient3, AdminAnnouncementNotification::class);
    }
}
