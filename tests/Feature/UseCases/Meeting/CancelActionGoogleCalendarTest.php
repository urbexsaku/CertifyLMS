<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use App\Services\GoogleCalendarService;
use App\UseCases\Meeting\CancelAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CancelActionGoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_google_calendar_event_after_meeting_is_canceled(): void
    {
        // Arrange
        $context = $this->createMeetingContext();

        $service = Mockery::mock(GoogleCalendarService::class);

        $service->shouldReceive('deleteMeetingEvent')
            ->once()
            ->with(Mockery::on(
                fn (Meeting $meeting) => $meeting->id === $context['meeting']->id
                    && $meeting->google_calendar_event_id === 'google-event-123'
            ))
            ->andReturnUsing(function (Meeting $meeting): void {
                $meeting->update([
                    'google_calendar_event_id' => null,
                ]);
            });

        $this->app->instance(GoogleCalendarService::class, $service);

        // Act
        app(CancelAction::class)($context['meeting'], $context['student']);

        $meeting = $context['meeting']->fresh();

        // Assert
        $this->assertSame(MeetingStatus::Canceled, $meeting->status);
        $this->assertNull($meeting->google_calendar_event_id);
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'status' => MeetingStatus::Canceled->value,
            'google_calendar_event_id' => null,
        ]);
    }

    public function test_skips_google_calendar_event_deletion_when_event_id_is_missing(): void
    {
        // Arrange
        $context = $this->createMeetingContext(
            eventId: null
        );

        // 実際の deleteMeetingEvent を使用し、
        // イベントIDがない場合に calendarClient が呼ばれないことを検証
        $service = Mockery::mock(
            GoogleCalendarService::class
        )->makePartial();

        $service->shouldNotReceive('calendarClient');

        $this->app->instance(GoogleCalendarService::class, $service);

        // Act
        app(CancelAction::class)($context['meeting'], $context['student']);

        $meeting = $context['meeting']->fresh();

        // Assert
        $this->assertSame(MeetingStatus::Canceled, $meeting->status);
        $this->assertNull($meeting->google_calendar_event_id);
    }

    public function test_keeps_cancellation_when_google_calendar_event_deletion_fails(): void
    {
        // Arrange
        $context = $this->createMeetingContext();

        $service = Mockery::mock(GoogleCalendarService::class);

        $service->shouldReceive('deleteMeetingEvent')
            ->once()
            ->andThrow(new RuntimeException('Google API error'));

        $this->app->instance(GoogleCalendarService::class, $service);

        Log::spy();

        // Act
        app(CancelAction::class)($context['meeting'], $context['student']);

        $meeting = $context['meeting']->fresh();

        // Assert: Google API が失敗してもキャンセルは成立する
        $this->assertSame(MeetingStatus::Canceled, $meeting->status);
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'status' => MeetingStatus::Canceled->value,
        ]);

        // Assert: 面談回数が返却される
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $context['student']->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => 1,
            'related_meeting_id' => $meeting->id,
        ]);

        // Assert: 既存のキャンセル通知が成立する
        $this->assertTrue(
            $context['coach']->notifications()
                ->where('type', MeetingCanceledNotification::class)
                ->exists()
        );

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'Google Calendar の面談予定削除に失敗しました。',
                Mockery::on(
                    fn (array $context) => $context['meeting_id'] === $meeting->id
                        && $context['exception'] === 'Google API error'
                )
            );
    }

    private function createMeetingContext(
        ?string $eventId = 'google-event-123'
    ): array {
        $student = User::factory()->student()->inProgress()->create([
            'max_meetings' => 5,
        ]);

        $coach = User::factory()->coach()->inProgress()->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
                'google_calendar_event_id' => $eventId,
            ]);

        return compact('student', 'coach', 'meeting');
    }
}
