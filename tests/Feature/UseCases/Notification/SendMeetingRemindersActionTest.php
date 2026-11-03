<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Notification;

use App\Enums\MeetingReminderWindow;
use App\Enums\UserRole;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use App\UseCases\Notification\SendMeetingRemindersAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendMeetingRemindersActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-07 17:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sends_eve_reminder_for_tomorrows_reserved_meetings(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => '2026-11-08 17:00:00',
        ]);

        // Act
        app(SendMeetingRemindersAction::class)(MeetingReminderWindow::Eve);

        // Assert
        foreach ([$student, $coach] as $user) {
            $this->assertDatabaseHas('notifications', [
                'notifiable_id' => $user->id,
                'notifiable_type' => $user->getMorphClass(),
                'type' => MeetingReminderNotification::class,
                'data->meeting_id' => $meeting->id,
                'data->window' => MeetingReminderWindow::Eve->value,
            ]);
        }
    }

    public function test_eve_reminder_excludes_meetings_outside_tomorrow(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->inProgress()->create();
        $todayMeeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => '2026-11-07 20:00:00',
        ]);
        $dayAfterTomorrowMeeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => '2026-11-09 17:00:00',
        ]);

        // Act
        app(SendMeetingRemindersAction::class)(MeetingReminderWindow::Eve);

        // Assert
        foreach ([$student, $coach] as $user) {
            foreach ([$todayMeeting, $dayAfterTomorrowMeeting] as $meeting) {
                $this->assertDatabaseMissing('notifications', [
                    'notifiable_id' => $user->id,
                    'notifiable_type' => $user->getMorphClass(),
                    'type' => MeetingReminderNotification::class,
                    'data->meeting_id' => $meeting->id,
                ]);
            }
        }
    }

    public function test_eve_reminder_excludes_non_reserved_meetings(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->inProgress()->create();
        $canceledMeeting = Meeting::factory()->canceled()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => '2026-11-08 17:00:00',
        ]);
        $completedTomorrowMeeting = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => '2026-11-08 17:00:00',
        ]);

        // Act
        app(SendMeetingRemindersAction::class)(MeetingReminderWindow::Eve);

        // Assert
        foreach ([$student, $coach] as $user) {
            foreach ([$canceledMeeting, $completedTomorrowMeeting] as $meeting) {
                $this->assertDatabaseMissing('notifications', [
                    'notifiable_id' => $user->id,
                    'notifiable_type' => $user->getMorphClass(),
                    'type' => MeetingReminderNotification::class,
                    'data->meeting_id' => $meeting->id,
                ]);
            }
        }
    }

    public function test_sends_one_hour_before_reminder_for_meetings_in_five_minute_window(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->inProgress()->create();
        $targetMeeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => '2026-11-07 18:00:00',
        ]);
        $outOfTargetMeeting1 = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => '2026-11-07 17:55:00',
        ]);
        $outOfTargetMeeting2 = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => '2026-11-07 18:05:00',
        ]);

        // Act
        app(SendMeetingRemindersAction::class)(MeetingReminderWindow::OneHourBefore);

        // Assert
        foreach ([$student, $coach] as $user) {
            $this->assertDatabaseHas('notifications', [
                'notifiable_id' => $user->id,
                'notifiable_type' => $user->getMorphClass(),
                'type' => MeetingReminderNotification::class,
                'data->meeting_id' => $targetMeeting->id,
                'data->window' => MeetingReminderWindow::OneHourBefore->value,
            ]);
        }

        foreach ([$student, $coach] as $user) {
            foreach ([$outOfTargetMeeting1, $outOfTargetMeeting2] as $meeting) {
                $this->assertDatabaseMissing('notifications', [
                    'notifiable_id' => $user->id,
                    'notifiable_type' => $user->getMorphClass(),
                    'type' => MeetingReminderNotification::class,
                    'data->meeting_id' => $meeting->id,
                ]);
            }
        }
    }

    public function test_one_hour_before_reminder_excludes_non_reserved_meetings(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->inProgress()->create();
        $canceledMeeting = Meeting::factory()->canceled()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => '2026-11-07 18:00:00',
        ]);
        $completedTomorrowMeeting = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => '2026-11-07 18:00:00',
        ]);

        // Act
        app(SendMeetingRemindersAction::class)(MeetingReminderWindow::OneHourBefore);

        // Assert
        foreach ([$student, $coach] as $user) {
            foreach ([$canceledMeeting, $completedTomorrowMeeting] as $meeting) {
                $this->assertDatabaseMissing('notifications', [
                    'notifiable_id' => $user->id,
                    'notifiable_type' => $user->getMorphClass(),
                    'type' => MeetingReminderNotification::class,
                    'data->meeting_id' => $meeting->id,
                ]);
            }
        }
    }

    public function test_does_not_send_reminders_to_ineligible_users(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();

        $ineligibleUsers = [
            User::factory()->student()->invited()->create(),
            User::factory()->student()->graduated()->create(),
            User::factory()->student()->withdrawn()->create(),
            User::factory()->coach()->invited()->create(),
        ];

        foreach ($ineligibleUsers as $user) {
            Meeting::factory()
                ->reserved()
                ->forCoach($user->role === UserRole::Coach ? $user : $coach)
                ->forStudent($user->role === UserRole::Student ? $user : User::factory()->student()->inProgress()->create())
                ->create([
                    'scheduled_at' => '2026-11-08 17:00:00',
                ]);
        }

        // Act
        app(SendMeetingRemindersAction::class)(MeetingReminderWindow::Eve);

        // Assert
        foreach ($ineligibleUsers as $user) {
            $this->assertDatabaseMissing('notifications', [
                'notifiable_id' => $user->id,
                'notifiable_type' => $user->getMorphClass(),
                'type' => MeetingReminderNotification::class,
                'data->window' => MeetingReminderWindow::Eve->value,
            ]);
        }
    }

    public function test_does_not_send_duplicate_reminders_on_sequential_reruns(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => '2026-11-08 17:00:00',
        ]);

        // Act
        // Act
        $action = app(SendMeetingRemindersAction::class);

        // 1回目：通知を作成
        $action(MeetingReminderWindow::Eve);

        // 2回目：同じ条件で再実行
        $action(MeetingReminderWindow::Eve);

        // Assert
        foreach ([$student, $coach] as $user) {
            $notificationCount = DatabaseNotification::query()
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', $user->getMorphClass())
                ->where('type', MeetingReminderNotification::class)
                ->where('data->meeting_id', $meeting->id)
                ->where('data->window', MeetingReminderWindow::Eve->value)
                ->count();

            $this->assertSame(1, $notificationCount);
        }
    }

    public function test_skips_reminder_when_lock_is_held_by_another_process(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => '2026-11-08 17:00:00',
        ]);

        $window = MeetingReminderWindow::Eve;

        // 別プロセスが同じ面談のロックを取得した状態を再現
        $lock = Cache::lock(
            "meeting-reminder:{$meeting->id}:{$window->value}",
            600
        );

        $this->assertTrue($lock->get());

        try {
            // Act
            app(SendMeetingRemindersAction::class)($window);

            // Assert
            foreach ([$student, $coach] as $user) {
                $this->assertDatabaseMissing('notifications', [
                    'notifiable_id' => $user->id,
                    'notifiable_type' => $user->getMorphClass(),
                    'type' => MeetingReminderNotification::class,
                    'data->meeting_id' => $meeting->id,
                    'data->window' => $window->value,
                ]);
            }
        } finally {
            $lock->release();
        }

        // ロック解放後は通知を送信できる
        app(SendMeetingRemindersAction::class)($window);

        foreach ([$student, $coach] as $user) {
            $this->assertDatabaseHas('notifications', [
                'notifiable_id' => $user->id,
                'notifiable_type' => $user->getMorphClass(),
                'type' => MeetingReminderNotification::class,
                'data->meeting_id' => $meeting->id,
                'data->window' => $window->value,
            ]);
        }
    }

    public function test_database_notification_contains_meeting_details_and_route_params(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => '2026-11-08 17:00:00',
        ]);

        // Act
        app(SendMeetingRemindersAction::class)(MeetingReminderWindow::Eve);

        // Assert
        foreach ([$student, $coach] as $user) {
            $notification = $user->notifications()
                ->where('type', MeetingReminderNotification::class)
                ->where('data->meeting_id', $meeting->id)
                ->where('data->window', MeetingReminderWindow::Eve->value)
                ->first();

            $this->assertNotNull($notification);

            $this->assertSame('meeting_reminder', $notification->data['notification_type']);
            $this->assertSame($meeting->id, $notification->data['meeting_id']);
            $this->assertSame(MeetingReminderWindow::Eve->value, $notification->data['window']);
            $this->assertSame(['meeting' => $meeting->id], $notification->data['route_params']);
        }
    }

    public function test_sends_reminder_email_with_meeting_details(): void
    {
        // Arrange
        Notification::fake();

        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => '2026-11-08 17:00:00',
        ]);

        // Act
        app(SendMeetingRemindersAction::class)(MeetingReminderWindow::Eve);

        // Assert
        Notification::assertSentTo(
            $coach,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels): bool {
                return in_array('mail', $channels, true);
            }
        );

        Notification::assertSentTo(
            $student,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels): bool {
                return in_array('mail', $channels, true);
            }
        );
    }
}
