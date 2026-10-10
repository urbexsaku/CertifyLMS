<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\ChatRoom;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use App\Notifications\MeetingCanceledNotification;
use App\Notifications\MeetingReservedNotification;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

class MarkAsReadTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_mark_as_read_updates_notification_read_at(): void
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

        $this->actingAs($student)->post(route('notifications.markAsRead', $notification->id));

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_mark_as_read_redirects_to_chat_room_for_chat_notification(): void
    {
        $student = User::factory()->student()->create();
        $room = ChatRoom::factory()->create();

        $notification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ChatMessageReceivedNotification::class,
            'data' => [
                'notification_type' => 'chat_message_received',
                'route' => 'chat.show',
                'route_params' => [
                    'room' => $room->id,
                ],
                'title' => '新しいチャットメッセージがあります',
                'message' => '新しいチャットメッセージを受信しました。',
                'body' => '新しいチャットメッセージを受信しました。',
            ],
            'read_at' => null,
        ]);

        $response = $this->actingAs($student)->post(route('notifications.markAsRead', $notification->id));

        $response->assertRedirect(route('chat.show', $room->id));

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_mark_as_read_redirects_to_qa_thread_for_qa_notification(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->forUser($student)->create();

        $notification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => QaReplyReceivedNotification::class,
            'data' => [
                'notification_type' => 'qa_reply_received',
                'route' => 'qa-board.show',
                'route_params' => [
                    'thread' => $thread->id,
                ],
                'title' => 'Q&Aに回答がありました',
                'message' => '投稿したQ&Aに回答がありました。',
                'body' => '投稿したQ&Aに回答がありました。',
            ],
            'read_at' => null,
        ]);

        $response = $this->actingAs($student)->post(route('notifications.markAsRead', $notification->id));

        $response->assertRedirect(route('qa-board.show', $thread->id));

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_mark_as_read_redirects_to_meetings_index_for_reserved_notification(): void
    {
        $student = User::factory()->student()->create();

        $notification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingReservedNotification::class,
            'data' => [
                'notification_type' => 'meeting_reserved',
                'title' => '面談が予約されました',
                'message' => '面談が予約されました。',
                'body' => '面談が予約されました。',
            ],
            'read_at' => null,
        ]);

        $response = $this->actingAs($student)->post(route('notifications.markAsRead', $notification->id));

        $response->assertRedirect(route('meetings.index'));

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_mark_as_read_redirects_to_meetings_index_for_canceled_notification(): void
    {
        $student = User::factory()->student()->create();

        $notification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '面談が予約されました',
                'message' => '面談が予約されました。',
                'body' => '面談が予約されました。',
            ],
            'read_at' => null,
        ]);

        $response = $this->actingAs($student)->post(route('notifications.markAsRead', $notification->id));

        $response->assertRedirect(route('meetings.index'));

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_mark_as_read_falls_back_to_notification_index_for_invalid_route_data(): void
    {
        $student = User::factory()->student()->create();

        $notification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ChatMessageReceivedNotification::class,
            'data' => [
                'notification_type' => 'unknown_notification_type',
                'route' => 'chat.show',
                'route_params' => [
                    'room' => (string) Str::uuid(),
                ],
                'title' => '新しいチャットメッセージがあります',
                'message' => '新しいチャットメッセージを受信しました。',
                'body' => '新しいチャットメッセージを受信しました。',
                ],
            'read_at' => null,
        ]);

        $response = $this->actingAs($student)->post(route('notifications.markAsRead', $notification->id));

        $response->assertRedirect(route('notifications.index'));

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_mark_as_read_redirects_to_notification_index_for_unknown_notification_type(): void
    {
        $student = User::factory()->student()->create();

        $notification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ChatMessageReceivedNotification::class,
            'data' => [
                'notification_type' => 'chat_message_received',
                'route' => 'chat.show',
                'route_params' => [],
                'title' => '新しいチャットメッセージがあります',
                'message' => '新しいチャットメッセージを受信しました。',
                'body' => '新しいチャットメッセージを受信しました。',
                ],
            'read_at' => null,
        ]);

        $response = $this->actingAs($student)->post(route('notifications.markAsRead', $notification->id));

        $response->assertRedirect(route('notifications.index'));

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $notification = $otherStudent->notifications()->create([
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

        $response = $this->actingAs($student)->post(route('notifications.markAsRead', $notification->id));

        $response->assertForbidden();
    }
}
