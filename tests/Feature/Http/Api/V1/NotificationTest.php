<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Api\V1;

use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_notifications_as_json(): void
    {
        // Arrange
        $user = User::factory()->student()->create();

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
        $response = $this->actingAs($user)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'notification_type',
                        'title',
                        'preview',
                        'created_at',
                        'relative_time',
                        'read_at',
                        'route',
                        'route_params',
                    ],
                ],
            ])
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.notification_type', 'meeting_canceled')
            ->assertJsonPath('data.0.title', '面談がキャンセルされました')
            ->assertJsonPath('data.0.preview', '予定されていた面談がキャンセルされました。')
            ->assertJsonPath('data.0.route', 'meetings.index')
            ->assertJsonPath('data.0.route_params', [])
            ->assertJsonPath('data.0.read_at', null)
            ->assertJsonPath('unread_count', 1);
    }

    public function test_notification_api_returns_only_authenticated_users_notifications(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $otherUser = User::factory()->student()->create();

        $ownNotification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '自分の通知',
                'message' => '自分宛ての通知です。',
                'body' => '自分宛ての通知です。',
            ],
            'read_at' => null,
        ]);

        $otherUser->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '他の人の通知',
                'message' => '他の人宛ての通知です。',
                'body' => '他の人宛ての通知です。',
            ],
            'read_at' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownNotification->id)
            ->assertJsonPath('unread_count', 1)
            ->assertJsonMissing(['title' => '他の人の通知']);
    }

    public function test_unread_tab_returns_only_unread_notifications(): void
    {
        // Arrange
        $user = User::factory()->student()->create();

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '未読通知',
                'message' => '未読通知です。',
                'body' => '未読通知です。',
            ],
            'read_at' => null,
        ]);

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '既読通知',
                'message' => '既読通知です。',
                'body' => '既読通知です。',
            ],
            'read_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson(route('api.v1.notifications.index', ['tab' => 'unread']));

        // Assert
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', '未読通知');
    }

    public function test_invalid_tab_defaults_to_all_notifications(): void
    {
        // Arrange
        $user = User::factory()->student()->create();

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '未読通知',
                'message' => '未読通知です。',
                'body' => '未読通知です。',
            ],
            'read_at' => null,
        ]);

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '既読通知',
                'message' => '既読通知です。',
                'body' => '既読通知です。',
            ],
            'read_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson(route('api.v1.notifications.index', ['tab' => 'invalid']));

        // Assert
        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('unread_count', 1);
    }

    public function test_guest_cannot_get_notifications_from_api(): void
    {
        // Act
        $response = $this->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_mark_own_notification_as_read(): void
    {
        // Arrange
        $user = User::factory()->student()->create();

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
        $response = $this->actingAs($user)
            ->postJson(route('api.v1.notifications.read', $notification->id));

        // Assert
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('notification_id', $notification->id);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read_through_api(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $otherUser = User::factory()->student()->create();

        $notification = $otherUser->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '他の人の通知',
                'message' => '他の人宛ての通知です。',
                'body' => '他の人宛ての通知です。',
            ],
            'read_at' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->postJson(route('api.v1.notifications.read', $notification->id));

        // Assert
        $response->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_guest_cannot_mark_notification_as_read_through_api(): void
    {
        // Arrange
        $user = User::factory()->student()->create();

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
        $response = $this->postJson(
            route('api.v1.notifications.read', $notification->id)
        );

        // Assert
        $response->assertUnauthorized();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_authenticated_user_can_mark_all_own_notifications_as_read(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $otherUser = User::factory()->student()->create();

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '未読通知',
                'message' => '未読通知です。',
                'body' => '未読通知です。',
            ],
            'read_at' => null,
        ]);

        $otherUser->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '他の人の通知',
                'message' => '他の人宛ての通知です。',
                'body' => '他の人宛ての通知です。',
            ],
            'read_at' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->postJson(route('api.v1.notifications.readAll'));

        // Assert
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('marked_count', 1)
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $otherUser->unreadNotifications()->count());
    }

    public function test_guest_cannot_mark_all_notifications_as_read_through_api(): void
    {
        // Arrange
        $user = User::factory()->student()->create();

        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '未読通知',
                'message' => '未読通知です。',
                'body' => '未読通知です。',
            ],
            'read_at' => null,
        ]);

        // Act
        $response = $this->postJson(route('api.v1.notifications.readAll'));

        // Assert
        $response->assertUnauthorized();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_notifications_are_ordered_by_created_at_descending(): void
    {
        // Arrange
        $user = User::factory()->student()->create();

        $oldNotification = $this->createNotification(
            $user,
            ['title' => '古い通知'],
            now()->subHours(3),
        );

        $newNotification = $this->createNotification(
            $user,
            ['title' => '新しい通知'],
            now()->subMinutes(5),
        );

        // Act
        $response = $this->actingAs($user)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.0.id', $newNotification->id)
            ->assertJsonPath('data.1.id', $oldNotification->id);
    }

    public function test_notification_api_returns_latest_20_notifications(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $notifications = [];

        for ($i = 1; $i <= 21; $i++) {
            $notifications[] = $this->createNotification(
                $user,
                ['title' => "通知{$i}"],
                now()->subMinutes(21 - $i),
            );
        }

        // Act
        $response = $this->actingAs($user)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('data.0.id', $notifications[20]->id)
            ->assertJsonMissing(['id' => $notifications[0]->id])
            ->assertJsonPath('unread_count', 21);
    }

    public function test_notification_api_returns_empty_data_when_no_notifications_exist(): void
    {
        // Arrange
        $user = User::factory()->student()->create();

        // Act
        $response = $this->actingAs($user)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertOk()
            ->assertExactJson([
                'data' => [],
                'unread_count' => 0,
            ]);
    }

    public function test_notification_api_uses_body_when_message_is_missing(): void
    {
        // Arrange
        $user = User::factory()->student()->create();

        $notification = $this->createNotification($user, [
            'notification_type' => 'meeting_canceled',
            'title' => '面談通知',
            'message' => null,
            'body' => 'bodyに設定された通知本文です。',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.preview', 'bodyに設定された通知本文です。');
    }

    public function test_notification_api_uses_body_preview_when_message_is_missing(): void
    {
        // Arrange
        $user = User::factory()->student()->create();

        $notification = $this->createNotification($user, [
            'notification_type' => 'meeting_canceled',
            'title' => '面談通知',
            'message' => null,
            'body_preview' => 'body_previewに設定された通知本文です。',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.preview', 'body_previewに設定された通知本文です。');
    }

    public function test_notification_api_returns_empty_values_for_missing_notification_data(): void
    {
        // Arrange
        $user = User::factory()->student()->create();

        $notification = $this->createNotification($user, []);

        // Act
        $response = $this->actingAs($user)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.notification_type', null)
            ->assertJsonPath('data.0.title', '通知')
            ->assertJsonPath('data.0.preview', '')
            ->assertJsonPath('data.0.route', 'notifications.index')
            ->assertJsonPath('data.0.route_params', []);
    }

    public function test_notification_api_returns_chat_route_and_params(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $roomId = (string) Str::uuid();

        $notification = $this->createNotification($user, [
            'notification_type' => 'chat_message_received',
            'title' => 'チャット通知',
            'message' => 'チャットメッセージが届きました。',
            'route_params' => ['room' => $roomId],
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.route', 'chat.show')
            ->assertJsonPath('data.0.route_params.room', $roomId);
    }

    public function test_notification_api_returns_qa_route_and_params(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $threadId = (string) Str::uuid();

        $notification = $this->createNotification($user, [
            'notification_type' => 'qa_reply_received',
            'title' => 'Q&A通知',
            'message' => 'Q&Aに回答がありました。',
            'route_params' => ['thread' => $threadId],
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.route', 'qa-board.show')
            ->assertJsonPath('data.0.route_params.thread', $threadId);
    }

    public function test_notification_api_returns_role_specific_meeting_routes(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();

        $studentReservedNotification = $this->createNotification($student, [
            'notification_type' => 'meeting_reserved',
            'title' => '面談予約通知',
        ]);

        $studentCanceledNotification = $this->createNotification($student, [
            'notification_type' => 'meeting_canceled',
            'title' => '面談キャンセル通知',
        ]);

        $coachReservedNotification = $this->createNotification($coach, [
            'notification_type' => 'meeting_reserved',
            'title' => '面談予約通知',
        ]);

        $coachCanceledNotification = $this->createNotification($coach, [
            'notification_type' => 'meeting_canceled',
            'title' => '面談キャンセル通知',
        ]);

        // Act
        $studentResponse = $this->actingAs($student)
            ->getJson(route('api.v1.notifications.index'));

        $coachResponse = $this->actingAs($coach)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $studentResponse->assertOk()
            ->assertJsonFragment([
                'id' => $studentReservedNotification->id,
                'route' => 'meetings.index',
            ])
            ->assertJsonFragment([
                'id' => $studentCanceledNotification->id,
                'route' => 'meetings.index',
            ]);

        $coachResponse->assertOk()
            ->assertJsonFragment([
                'id' => $coachReservedNotification->id,
                'route' => 'coach.meetings.index',
            ])
            ->assertJsonFragment([
                'id' => $coachCanceledNotification->id,
                'route' => 'coach.meetings.index',
            ]);
    }

    public function test_notification_api_returns_meeting_reminder_route_and_params(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $meetingId = (string) Str::uuid();

        $notification = $this->createNotification($user, [
            'notification_type' => 'meeting_reminder',
            'title' => '面談リマインダー',
            'message' => '面談開始前のお知らせです。',
            'route_params' => ['meeting' => $meetingId],
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.route', 'meetings.show')
            ->assertJsonPath('data.0.route_params.meeting', $meetingId);
    }

    public function test_notification_api_returns_admin_announcement_route_and_params(): void
    {
        // Arrange
        $user = User::factory()->student()->create();

        $notification = $this->createNotification($user, [
            'notification_type' => 'admin_announcement',
            'title' => '管理者からのお知らせ',
            'message' => 'お知らせ本文です。',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.route', 'notifications.show')
            ->assertJsonPath('data.0.route_params.notification', $notification->id);
    }

    public function test_notification_api_returns_notification_index_route_for_unknown_type(): void
    {
        // Arrange
        $user = User::factory()->student()->create();

        $notification = $this->createNotification($user, [
            'notification_type' => 'unknown_notification_type',
            'title' => '不明な通知',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.route', 'notifications.index')
            ->assertJsonPath('data.0.route_params', []);
    }

    public function test_notification_api_returns_notification_index_route_for_missing_required_params(): void
    {
        // Arrange
        $user = User::factory()->student()->create();

        $notificationTypes = [
            'chat_message_received',
            'qa_reply_received',
            'meeting_reminder',
        ];

        $notifications = [];

        foreach ($notificationTypes as $type) {
            $notifications[] = $this->createNotification($user, [
                'notification_type' => $type,
                'title' => $type,
            ]);
        }

        // Act
        $response = $this->actingAs($user)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertOk();

        foreach ($notifications as $notification) {
            $response->assertJsonFragment([
                'id' => $notification->id,
                'route' => 'notifications.index',
                'route_params' => [],
            ]);
        }
    }

    public function test_notification_api_returns_notification_index_route_for_invalid_required_params(): void
    {
        // Arrange
        $user = User::factory()->student()->create();

        $notifications = [
            $this->createNotification($user, [
                'notification_type' => 'chat_message_received',
                'title' => '不正なチャット通知',
                'route_params' => ['room' => []],
            ]),
            $this->createNotification($user, [
                'notification_type' => 'qa_reply_received',
                'title' => '不正なQ&A通知',
                'route_params' => ['thread' => []],
            ]),
            $this->createNotification($user, [
                'notification_type' => 'meeting_reminder',
                'title' => '不正な面談通知',
                'route_params' => ['meeting' => []],
            ]),
        ];

        // Act
        $response = $this->actingAs($user)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertOk();

        foreach ($notifications as $notification) {
            $response->assertJsonFragment([
                'id' => $notification->id,
                'route' => 'notifications.index',
                'route_params' => [],
            ]);
        }
    }

    public function test_authenticated_user_can_mark_already_read_notification_as_read(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $readAt = now()->subDay()->startOfSecond();

        $notification = $this->createNotification(
            $user,
            [
                'notification_type' => 'meeting_canceled',
                'title' => '既読済み通知',
            ],
            readAt: $readAt,
        );

        // Act
        $response = $this->actingAs($user)
            ->postJson(route('api.v1.notifications.read', $notification->id));

        // Assert
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('notification_id', $notification->id);

        $this->assertSame(
            $readAt->toISOString(),
            $notification->fresh()->read_at?->toISOString(),
        );
    }

    public function test_notification_read_api_returns_not_found_for_nonexistent_notification(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $notificationId = (string) Str::uuid();

        // Act
        $response = $this->actingAs($user)
            ->postJson(route('api.v1.notifications.read', $notificationId));

        // Assert
        $response->assertNotFound();
    }

    public function test_mark_all_notifications_as_read_does_not_change_already_read_notifications(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $readAt = now()->subDay();

        $readNotification = $this->createNotification(
            $user,
            [
                'notification_type' => 'meeting_canceled',
                'title' => '既読通知',
            ],
            readAt: $readAt,
        );

        $readAt = $readNotification->fresh()->read_at?->toISOString();

        $unreadNotification = $this->createNotification($user, [
            'notification_type' => 'meeting_canceled',
            'title' => '未読通知',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->postJson(route('api.v1.notifications.readAll'));

        // Assert
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('marked_count', 1)
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(
            $readAt,
            $readNotification->fresh()->read_at?->toISOString(),
        );

        $this->assertNotNull($unreadNotification->fresh()->read_at);
    }

    public function test_mark_all_notifications_as_read_returns_zero_when_no_unread_notifications_exist(): void
    {
        // Arrange
        $user = User::factory()->student()->create();

        $this->createNotification(
            $user,
            [
                'notification_type' => 'meeting_canceled',
                'title' => '既読通知',
            ],
            readAt: now(),
        );

        // Act
        $response = $this->actingAs($user)
            ->postJson(route('api.v1.notifications.readAll'));

        // Assert
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('marked_count', 0)
            ->assertJsonPath('unread_count', 0);
    }

    /**
     * テスト用の通知を作成する。
     *
     * @param array<string, mixed> $data
     */
    private function createNotification(
        User $user,
        array $data,
        ?Carbon $createdAt = null,
        ?Carbon $readAt = null,
    ): DatabaseNotification {
        $attributes = [
            'id' => (string) Str::uuid(),
            'type' => MeetingCanceledNotification::class,
            'data' => $data,
            'read_at' => $readAt,
        ];

        if ($createdAt !== null) {
            $attributes['created_at'] = $createdAt;
        }

        return $user->notifications()->create($attributes);
    }
}
