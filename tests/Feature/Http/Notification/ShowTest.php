<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_can_view_full_announcement_body(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->create([
            'title' => 'お知らせテスト',
            'body' => 'お知らせ本文',
        ]);

        $student->notify(new AdminAnnouncementNotification($announcement));

        $notification = $student->notifications()->firstOrFail();

        $response = $this->actingAs($student)->get(route('notifications.show', $notification));

        $response->assertOk();
        $response->assertViewIs('notifications.show');
        $response->assertSee('お知らせテスト');
        $response->assertSee('お知らせ本文');
    }

    public function test_other_user_cannot_view_announcement_notification(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->create([
            'title' => 'お知らせテスト',
            'body' => 'お知らせ本文',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $student->id,
        ]);

        $student->notify(new AdminAnnouncementNotification($announcement));

        $notification = $student->notifications()->firstOrFail();

        $response = $this->actingAs($otherStudent)->get(route('notifications.show', $notification));

        $response->assertForbidden();
    }
}
