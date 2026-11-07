<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use App\UseCases\Meeting\CancelAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_throws_when_meeting_is_not_reserved(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $meeting = Meeting::factory()->canceled()->forStudent($student)->create();

        $this->expectException(MeetingStatusTransitionException::class);

        app(CancelAction::class)($meeting, $student);
    }

    public function test_throws_when_meeting_has_already_started(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forStudent($student)->create([
            'scheduled_at' => now()->subDay(),
        ]);

        // Act
        try {
            app(CancelAction::class)($meeting, $student);

            $this->fail('開始済みの面談をキャンセルした場合は例外が発生するはず');
        } catch (MeetingAlreadyStartedException) {
            // 期待した例外
        }

        // Assert
        $meeting->refresh();

        $this->assertSame(MeetingStatus::Reserved, $meeting->status);
        $this->assertNull($meeting->canceled_by_user_id);
        $this->assertNull($meeting->canceled_at);
    }

    public function test_cancels_meeting_and_refunds_quota(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $this->actingAs($student);
        $meeting = Meeting::factory()->reserved()->inFuture()->forStudent($student)->create();

        // Act
        app(CancelAction::class)($meeting, $student);

        // Assert
        $meeting->refresh();

        $this->assertSame(MeetingStatus::Canceled, $meeting->status);
        $this->assertSame($student->id, $meeting->canceled_by_user_id);
        $this->assertNotNull($meeting->canceled_at);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => 1,
            'related_meeting_id' => $meeting->id,
        ]);
    }

    public function test_notifies_coach_when_student_cancels(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        // Act
        app(CancelAction::class)($meeting, $student);

        // Assert
        $notification = $coach->notifications()
            ->where('type', MeetingCanceledNotification::class)
            ->first();

        $this->assertNotNull($notification);

        $data = $notification->data;
        $this->assertSame('meeting_canceled', $data['notification_type']);

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $student->id,
            'notifiable_type' => $student->getMorphClass(),
            'type' => MeetingCanceledNotification::class,
        ]);
    }

    public function test_notifies_student_when_coach_cancels(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        // Act
        app(CancelAction::class)($meeting, $coach);

        // Assert
        $notification = $student->notifications()
            ->where('type', MeetingCanceledNotification::class)
            ->first();

        $this->assertNotNull($notification);

        $data = $notification->data;
        $this->assertSame('meeting_canceled', $data['notification_type']);

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $coach->id,
            'notifiable_type' => $coach->getMorphClass(),
            'type' => MeetingCanceledNotification::class,
        ]);
    }

    public function test_does_not_notify_when_cancellation_fails(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subDay(),
        ]);

        // Act
        try {
            app(CancelAction::class)($meeting, $student);

            $this->fail('開始済みの面談をキャンセルした場合は例外が発生するはず');
        } catch (MeetingAlreadyStartedException) {
            // 期待した例外
        }

        // Assert
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $coach->id,
            'notifiable_type' => $coach->getMorphClass(),
            'type' => MeetingCanceledNotification::class,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $student->id,
            'notifiable_type' => $student->getMorphClass(),
            'type' => MeetingCanceledNotification::class,
        ]);
    }
}
