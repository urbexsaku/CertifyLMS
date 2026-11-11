<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use App\UseCases\Announcement\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_announcement_is_created_and_dispatched(): void
    {
        // Arrange
        Notification::fake();

        $inProgressStudent = User::factory()->student()->inProgress()->create();
        $graduatedStudent = User::factory()->student()->graduated()->create();
        $withdrawnStudent = User::factory()->student()->withdrawn()->create();
        $invitedStudent = User::factory()->student()->invited()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->inProgress()->create();

        $validated = [
            'title' => 'お知らせテスト',
            'body' => 'お知らせ本文',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ];

        // Act
        $announcement = app(StoreAction::class)($admin, $validated);

        // Assert
        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'created_by_user_id' => $admin->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'dispatched_count' => 1,
        ]);

        $this->assertNotNull($announcement->fresh()->dispatched_at);

        Notification::assertSentTo($inProgressStudent, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($graduatedStudent, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($withdrawnStudent, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($invitedStudent, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($coach, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($admin, AdminAnnouncementNotification::class);
    }

    public function test_announcement_history_is_created_when_recipients_are_empty(): void
    {
        // Arrange
        Notification::fake();
        $admin = User::factory()->admin()->inProgress()->create();

        $validated = [
            'title' => 'お知らせテスト',
            'body' => 'お知らせ本文',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ];

        // Act
        $announcement = app(StoreAction::class)($admin, $validated);

        // Assert
        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'created_by_user_id' => $admin->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'dispatched_count' => 0,
        ]);

        $this->assertNotNull($announcement->fresh()->dispatched_at);

        Notification::assertNothingSent();
    }
}
