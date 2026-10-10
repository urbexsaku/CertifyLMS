<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarkAllAsReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_all_as_read_updates_only_authenticated_users_notifications(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $ownNotification = $student->notifications()->create([
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

        $otherNotification = $otherStudent->notifications()->create([
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

        $this->actingAs($student)->post(route('notifications.markAllAsRead'));

        $ownNotification->refresh();
        $this->assertNotNull($ownNotification->read_at);

        $otherNotification->refresh();
        $this->assertNull($otherNotification->read_at);
    }

    public function test_mark_all_as_read_does_not_change_already_read_notifications(): void
    {
        $student = User::factory()->student()->create();

        $readNotification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '面談がキャンセルされました',
                'message' => '予定されていた面談がキャンセルされました。',
                'body' => '予定されていた面談がキャンセルされました。',
            ],
            'read_at' => now(),
        ]);

        $unreadNotification = $student->notifications()->create([
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

        $this->actingAs($student)->post(route('notifications.markAllAsRead'));

        $readNotification->refresh();
        $this->assertNotNull($readNotification->read_at);

        $unreadNotification->refresh();
        $this->assertNotNull($unreadNotification->read_at);
    }

    public function test_guest_cannot_mark_all_notifications_as_read(): void
    {
        $student = User::factory()->student()->create();

        $notification = $student->notifications()->create([
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

        $this->post(route('notifications.markAllAsRead'));

        $notification->refresh();
        $this->assertNull($notification->read_at);
    }
}
