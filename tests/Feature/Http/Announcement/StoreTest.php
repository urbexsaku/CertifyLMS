<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_dispatch_announcement(): void
    {
        // Arrange
        Notification::fake();
        $admin = User::factory()->admin()->inProgress()->create();
        $recipient = User::factory()->student()->inProgress()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'お知らせテスト',
            'body' => 'お知らせテスト本文',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);

        // Assert
        $announcement = Announcement::where('title', 'お知らせテスト')->firstOrFail();
        $response->assertRedirect(route('admin.announcements.show', $announcement));
        $response->assertSessionHas('success', 'お知らせを配信しました (1件)。');

        $this->assertDatabaseHas('announcements', [
            'created_by_user_id' => $admin->id,
            'title' => 'お知らせテスト',
            'body' => 'お知らせテスト本文',
            'dispatched_count' => 1,
        ]);

        $this->assertNotNull($announcement->fresh()->dispatched_at);

        Notification::assertSentTo($recipient, AdminAnnouncementNotification::class);
    }

    public function test_admin_can_dispatch_announcement_with_no_recipients(): void
    {
        // Arrange
        $admin = User::factory()->admin()->inProgress()->create();
        $cert = Certification::factory()->published()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'お知らせテスト',
            'body' => 'お知らせテスト本文',
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $cert->id,
        ]);

        // Assert
        $announcement = Announcement::where('title', 'お知らせテスト')->firstOrFail();
        $response->assertRedirect(route('admin.announcements.show', $announcement));
        $response->assertSessionHas('success', 'お知らせを配信しました (0件)。');

        $this->assertDatabaseHas('announcements', [
            'created_by_user_id' => $admin->id,
            'title' => 'お知らせテスト',
            'body' => 'お知らせテスト本文',
            'dispatched_count' => 0,
        ]);

        $this->assertNotNull($announcement->fresh()->dispatched_at);
    }
}
