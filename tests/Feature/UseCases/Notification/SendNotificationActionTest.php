<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Notification;

use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use App\UseCases\Notification\SendNotificationAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendNotificationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_is_sent_to_in_progress_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        app(SendNotificationAction::class)($student, new MeetingCanceledNotification);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $student->id,
            'notifiable_type' => $student->getMorphClass(),
            'type' => MeetingCanceledNotification::class,
        ]);
    }

    public function test_notification_is_sent_to_in_progress_coach(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        app(SendNotificationAction::class)($coach, new MeetingCanceledNotification);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $coach->id,
            'notifiable_type' => $coach->getMorphClass(),
            'type' => MeetingCanceledNotification::class,
        ]);
    }

    public function test_notification_is_not_sent_to_invited_user(): void
    {
        $student = User::factory()->student()->invited()->create();

        app(SendNotificationAction::class)($student, new MeetingCanceledNotification);

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $student->id,
            'notifiable_type' => $student->getMorphClass(),
            'type' => MeetingCanceledNotification::class,
        ]);
    }

    public function test_notification_is_not_sent_to_graduated_user(): void
    {
        $student = User::factory()->student()->graduated()->create();

        app(SendNotificationAction::class)($student, new MeetingCanceledNotification);

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $student->id,
            'notifiable_type' => $student->getMorphClass(),
            'type' => MeetingCanceledNotification::class,
        ]);
    }

    public function test_notification_is_not_sent_to_withdrawn_user(): void
    {
        $student = User::factory()->student()->withdrawn()->create();

        app(SendNotificationAction::class)($student, new MeetingCanceledNotification);

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $student->id,
            'notifiable_type' => $student->getMorphClass(),
            'type' => MeetingCanceledNotification::class,
        ]);
    }
}
