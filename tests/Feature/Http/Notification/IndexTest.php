<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use App\Notifications\MeetingCanceledNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_notification_index(): void
    {
        // Arrange
        $user = User::factory()->create();

        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '面談がキャンセルされました',
                'message' => '予定されていた面談がキャンセルされました。',
                'body' => '予定されていた面談がキャンセルされました。',
            ],
            'read_at' => null,
        ]);

        // Act
        $response = $this->actingAs($user)->get(route('notifications.index'));

        // Assert
        $response->assertOk();
        $response->assertViewIs('notifications.index');
        $response->assertViewHas('notifications', function ($notifications) use ($notification) {
            return $notifications->contains('id', $notification->id);
        });
    }

    public function test_guest_cannot_view_notification_index(): void
    {
        // Arrange
        $user = User::factory()->create();

        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '面談がキャンセルされました',
                'message' => '予定されていた面談がキャンセルされました。',
                'body' => '予定されていた面談がキャンセルされました。',
            ],
            'read_at' => null,
        ]);

        // Act
        $response = $this->get(route('notifications.index'));

        // Assert
        $response->assertRedirect(route('login'));
    }

    public function test_notifications_are_ordered_by_created_at_descending(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '古い通知',
                'message' => '予定されていた面談がキャンセルされました。',
                'body' => '予定されていた面談がキャンセルされました。',
            ],
            'read_at' => null,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '新しい通知',
                'message' => '予定されていた面談がキャンセルされました。',
                'body' => '予定されていた面談がキャンセルされました。',
            ],
            'read_at' => null,
            'created_at' => Carbon::now()->subMinutes(5),
        ]);

        // Act
        $response = $this->actingAs($student)->get(route('notifications.index'));

        // Assert
        $response->assertOk();
        $response->assertViewIs('notifications.index');
        $response->assertSeeInOrder([
            '新しい通知',
            '古い通知',
        ]);
    }

    public function test_paginates_20_per_page(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        for ($i = 1; $i <= 22; $i++) {
            $student->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => MeetingCanceledNotification::class,
                'data' => [
                    'notification_type' => 'meeting_canceled',
                    'title' => '未読通知',
                    'message' => '予定されていた面談がキャンセルされました。',
                    'body' => '予定されていた面談がキャンセルされました。',
                ],
                'read_at' => null,
            ]);
        }

        // Act
        $response = $this->actingAs($student)->get(route('notifications.index'));

        // Assert
        $response->assertOk();
        $notifications = $response->viewData('notifications');
        $this->assertSame(20, $notifications->perPage());
        $this->assertSame(22, $notifications->total());
    }

    public function test_unread_tab_displays_only_unread_notifications(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '未読通知',
                'message' => '予定されていた面談がキャンセルされました。',
                'body' => '予定されていた面談がキャンセルされました。',
            ],
            'read_at' => null,
        ]);

        $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '既読通知',
                'message' => '予定されていた面談がキャンセルされました。',
                'body' => '予定されていた面談がキャンセルされました。',
            ],
            'read_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($student)->get(route('notifications.index', ['tab' => 'unread']));

        // Assert
        $response->assertOk();
        $response->assertSee('未読通知');
        $response->assertDontSee('既読通知');
    }

    public function test_notification_index_does_not_display_other_users_notifications(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();

        $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '自分の通知',
                'message' => '予定されていた面談がキャンセルされました。',
                'body' => '予定されていた面談がキャンセルされました。',
            ],
            'read_at' => null,
        ]);

        $otherStudent->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '他の人の通知',
                'message' => '予定されていた面談がキャンセルされました。',
                'body' => '予定されていた面談がキャンセルされました。',
            ],
            'read_at' => null,
        ]);

        // Act
        $response = $this->actingAs($student)->get(route('notifications.index', ['tab' => 'unread']));

        // Assert
        $response->assertOk();
        $response->assertSee('自分の通知');
        $response->assertDontSee('他の人の通知');
    }

    public function test_admin_announcement_notification_is_displayed_in_recipient_list(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->create(['title' => 'お知らせテスト']);

        $student->notify(new AdminAnnouncementNotification($announcement));

        // Act
        $response = $this->actingAs($student)->get(route('notifications.index'));

        // Assert
        $response->assertOk();
        $response->assertSee('お知らせテスト');
    }
}
