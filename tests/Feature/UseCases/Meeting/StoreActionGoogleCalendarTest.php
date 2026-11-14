<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\GoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReservedNotification;
use App\Services\GoogleCalendarService;
use App\Services\MeetingQuotaService;
use App\UseCases\Meeting\StoreAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class StoreActionGoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_google_calendar_event_after_meeting_is_committed(): void
    {
        // Arrange
        $context = $this->createReservationContext();
        $service = $this->mockAvailableGoogleCalendar();
        $service->shouldReceive('createMeetingEvent')
            ->once()
            ->with(Mockery::on(
                fn (Meeting $meeting) => $meeting->exists
                    && $meeting->status === MeetingStatus::Reserved
                    && $meeting->student_id === $context['student']->id
                    && $meeting->coach_id === $context['coach']->id
            ))
            ->andReturnUsing(function (Meeting $meeting): string {
                $meeting->update([
                    'google_calendar_event_id' => 'google-event-123',
                ]);

                return 'google-event-123';
            });

        // Act
        $meeting = app(StoreAction::class)(
            $context['enrollment'],
            [
                'scheduled_at' => $context['scheduledAt']->format('Y-m-d\TH:i:s'),
                'topic' => '相談内容テスト',
            ]
        );

        $meeting->refresh();

        // Assert
        $this->assertSame(
            'google-event-123',
            $meeting->google_calendar_event_id
        );

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'status' => MeetingStatus::Reserved->value,
            'google_calendar_event_id' => 'google-event-123',
        ]);
    }

    public function test_does_not_create_google_calendar_event_when_coach_is_not_connected(): void
    {
        // Arrange
        $context = $this->createReservationContext(
            connected: false
        );

        $service = Mockery::mock(GoogleCalendarService::class);
        $service->shouldNotReceive('createMeetingEvent');
        $service->shouldNotReceive('busyPeriods');
        $this->app->instance(GoogleCalendarService::class, $service);

        // Act
        $meeting = app(StoreAction::class)(
            $context['enrollment'],
            [
                'scheduled_at' => $context['scheduledAt']->format('Y-m-d\TH:i:s'),
                'topic' => '相談内容テスト',
            ]
        );

        // Assert
        $this->assertSame(MeetingStatus::Reserved, $meeting->status);
        $this->assertNull($meeting->google_calendar_event_id);
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'status' => MeetingStatus::Reserved->value,
        ]);
    }

    public function test_keeps_meeting_when_google_calendar_event_creation_fails(): void
    {
        // Arrange
        $context = $this->createReservationContext();

        $service = $this->mockAvailableGoogleCalendar();

        $service->shouldReceive('createMeetingEvent')
            ->once()
            ->andThrow(new RuntimeException('Google API error'));

        Log::spy();

        // Act
        $meeting = app(StoreAction::class)(
            $context['enrollment'],
            [
                'scheduled_at' => $context['scheduledAt']->format('Y-m-d\TH:i:s'),
                'topic' => '相談内容テスト',
            ]
        );

        $meeting->refresh();

        // Assert: Google API が失敗しても LMS の予約は成立する
        $this->assertSame(
            MeetingStatus::Reserved,
            $meeting->status
        );

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'status' => MeetingStatus::Reserved->value,
        ]);

        // Assert: 面談回数の消費が成立する
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $context['student']->id,
            'type' => MeetingQuotaTransactionType::Consumed->value,
            'amount' => -1,
            'related_meeting_id' => $meeting->id,
        ]);

        $this->assertSame(
            2,
            app(MeetingQuotaService::class)->remaining(
                $context['student']
            )
        );

        // Assert: 既存のコーチ向け通知が成立する
        $this->assertTrue(
            $context['coach']->notifications()
                ->where('type', MeetingReservedNotification::class)
                ->exists()
        );

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'Google Calendar への面談予定登録に失敗しました。',
                Mockery::on(
                    fn (array $context) => $context['meeting_id'] === $meeting->id
                        && $context['exception'] === 'Google API error'
                )
            );
    }

    private function createReservationContext(
        bool $connected = true
    ): array {
        $student = User::factory()->student()->inProgress()->create([
            'max_meetings' => 3,
        ]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);

        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        if ($connected) {
            GoogleCredential::factory()->for($coach, 'user')->create();
        }

        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        return compact(
            'student',
            'coach',
            'certification',
            'enrollment',
            'scheduledAt'
        );
    }

    private function mockAvailableGoogleCalendar(): GoogleCalendarService
    {
        $service = Mockery::mock(GoogleCalendarService::class);

        // 空き時間取得は空の予定一覧を返す
        // validateSlot とコーチ割当判定の両方から呼ばれる
        $service->shouldReceive('busyPeriods')->zeroOrMoreTimes()->andReturn(collect());

        $this->app->instance(GoogleCalendarService::class, $service);

        return $service;
    }
}
