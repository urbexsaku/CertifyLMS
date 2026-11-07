<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingOutOfAvailabilityException;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReservedNotification;
use App\Services\MeetingQuotaService;
use App\UseCases\Meeting\StoreAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_meeting_and_consumes_quota(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create([
            'max_meetings' => 3,
        ]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        // Act
        $meeting = app(StoreAction::class)($enrollment, [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '相談内容テスト',
        ]);

        // Assert: 面談が予約済みで作成されている
        $this->assertSame(MeetingStatus::Reserved, $meeting->status);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'student_id' => $student->id,
            'coach_id' => $coach->id,
            'enrollment_id' => $enrollment->id,
            'status' => MeetingStatus::Reserved->value,
            'topic' => '相談内容テスト',
            'meeting_url_snapshot' => 'https://meet.example.com/coach-room',
        ]);

        // Assert: 面談回数の消費履歴が作成されている
        $this->assertNotNull($meeting->meeting_quota_transaction_id);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'id' => $meeting->meeting_quota_transaction_id,
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::Consumed->value,
            'amount' => -1,
            'related_meeting_id' => $meeting->id,
        ]);

        // Assert: 残面談回数が 3 回から 2 回に減っている
        $this->assertSame(2, app(MeetingQuotaService::class)->remaining($student));
    }

    public function test_throws_when_student_has_no_remaining_quota(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create([
            'max_meetings' => 0,
        ]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        // Act
        try {
            app(StoreAction::class)($enrollment, [
                'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
                'topic' => '相談内容テスト',
            ]);

            $this->fail('残面談回数が0の場合は例外が発生するはず');
        } catch (InsufficientMeetingQuotaException) {
            // 期待した例外
        }

        // Assert
        $this->assertDatabaseMissing('meetings', ['student_id' => $student->id]);

        $this->assertDatabaseMissing('meeting_quota_transactions', ['user_id' => $student->id]);
    }

    public function test_throws_when_slot_is_outside_availability(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create([
            'max_meetings' => 3,
        ]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(8, 0);

        // Act
        try {
            app(StoreAction::class)($enrollment, [
                'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
                'topic' => '相談内容テスト',
            ]);

            $this->fail('予約可能時間枠外の例外が発生するはず');
        } catch (MeetingOutOfAvailabilityException) {
            // 期待した例外
        }

        // Assert
        $this->assertDatabaseMissing('meetings', ['student_id' => $student->id]);

        $this->assertDatabaseMissing('meeting_quota_transactions', ['user_id' => $student->id]);
    }

    public function test_throws_when_all_coaches_are_already_booked_for_slot(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create([
            'max_meetings' => 3,
        ]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $otherStudent = User::factory()->student()->inProgress()->create();
        Meeting::factory()->reserved()->forCoach($coach)->forStudent($otherStudent)->create(['scheduled_at' => $scheduledAt]);

        // Act
        try {
            app(StoreAction::class)($enrollment, [
                'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
                'topic' => '相談内容テスト',
            ]);

            $this->fail('コーチの空きがない例外が発生するはず');
        } catch (MeetingOutOfAvailabilityException) {
            // 期待した例外
        }

        // Assert
        $this->assertDatabaseMissing('meetings', ['student_id' => $student->id]);

        $this->assertDatabaseMissing('meeting_quota_transactions', ['user_id' => $student->id]);
    }

    public function test_notifies_assigned_coach_when_meeting_is_created(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $unrelatedCoach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0); // 次の月曜 10:00(未来)

        // / Act
        $meeting = app(StoreAction::class)($enrollment, [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '相談内容テスト',
        ]);

        // Assert
        Notification::assertSentTo(
            $coach,
            MeetingReservedNotification::class,
            fn ($notification, $channels) => in_array('database', $channels, true)
                && in_array('mail', $channels, true),
        );

        Notification::assertNotSentTo($student, MeetingReservedNotification::class);
        Notification::assertNotSentTo($unrelatedCoach, MeetingReservedNotification::class);
    }

    public function test_does_not_notify_when_reservation_fails(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create([
            'max_meetings' => 0,
        ]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        // Act
        try {
            app(StoreAction::class)($enrollment, [
                'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
                'topic' => '相談内容テスト',
            ]);

            $this->fail('残面談回数が0の場合は例外が発生するはず');
        } catch (InsufficientMeetingQuotaException) {
            // 期待した例外
        }

        // Assert
        Notification::assertNotSentTo($coach, MeetingReservedNotification::class);
    }

    private function attachCoach(
        Certification $certification,
        User $coach,
        User $admin
    ): void {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }
}
