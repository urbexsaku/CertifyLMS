<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\Mentoring\MeetingOutOfAvailabilityException;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\GoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\Services\MeetingAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class MeetingAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function attachCoach(Certification $certification, User $coach): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    public function test_returns_60min_slots_for_active_availability(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $this->attachCoach($certification, $coach);

        $date = Carbon::parse('2026-06-01'); // Monday (dayOfWeek=1)
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '12:00:00')->create();

        $slots = app(MeetingAvailabilityService::class)->slotsForCertification($certification, $date);

        // 09-10 / 10-11 / 11-12 の 3 枠
        $this->assertCount(3, $slots);
        $this->assertSame('2026-06-01T09:00:00+09:00', $slots->first()['slot_start']->toIso8601String());
        $this->assertSame(1, $slots->first()['available_coach_count']);
    }

    public function test_excludes_existing_reserved_meetings(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $this->attachCoach($certification, $coach);

        $date = Carbon::parse('2026-06-01');
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '12:00:00')->create();
        // 10:00 にすでに予約あり
        Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => Carbon::parse('2026-06-01 10:00:00'),
        ]);

        $slots = app(MeetingAvailabilityService::class)->slotsForCertification($certification, $date);

        $times = $slots->map(fn (array $s) => $s['slot_start']->format('H:i'))->all();
        $this->assertEquals(['09:00', '11:00'], $times);
    }

    public function test_excludes_inactive_availability(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $this->attachCoach($certification, $coach);

        $date = Carbon::parse('2026-06-01');
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '11:00:00')->inactive()->create();

        $slots = app(MeetingAvailabilityService::class)->slotsForCertification($certification, $date);

        $this->assertCount(0, $slots);
    }

    public function test_unions_multiple_coaches_into_available_count(): void
    {
        $certification = Certification::factory()->published()->create();
        $coachA = User::factory()->coach()->create();
        $coachB = User::factory()->coach()->create();
        $this->attachCoach($certification, $coachA);
        $this->attachCoach($certification, $coachB);

        $date = Carbon::parse('2026-06-01');
        CoachAvailability::factory()->forCoach($coachA)->onDay(1)->timeRange('09:00:00', '10:00:00')->create();
        CoachAvailability::factory()->forCoach($coachB)->onDay(1)->timeRange('09:00:00', '10:00:00')->create();

        $slots = app(MeetingAvailabilityService::class)->slotsForCertification($certification, $date);

        $this->assertCount(1, $slots);
        $this->assertSame(2, $slots->first()['available_coach_count']);
    }

    public function test_validate_slot_throws_when_outside_availability(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $this->attachCoach($certification, $coach);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '10:00:00')->create();

        $this->expectException(MeetingOutOfAvailabilityException::class);

        // 月曜の枠は 09-10 のみ。15:00 は枠外
        app(MeetingAvailabilityService::class)->validateSlot($certification, Carbon::parse('2026-06-01 15:00:00'));
    }

    public function test_validate_slot_succeeds_when_in_availability(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $this->attachCoach($certification, $coach);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '12:00:00')->create();

        // 例外が起きないことを確認
        app(MeetingAvailabilityService::class)->validateSlot($certification, Carbon::parse('2026-06-01 09:00:00'));
        $this->addToAssertionCount(1);
    }

    public function test_excludes_slots_overlapping_google_calendar_busy_periods(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $this->attachCoach($certification, $coach);

        GoogleCredential::factory()
            ->for($coach, 'user')
            ->create();

        $date = Carbon::parse('2026-06-01');

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '12:00:00')
            ->create();

        $service = Mockery::mock(GoogleCalendarService::class);

        $service->shouldReceive('busyPeriods')
            ->once()
            ->with(
                Mockery::on(fn (User $user) => $user->id === $coach->id),
                Mockery::type(Carbon::class),
                Mockery::type(Carbon::class),
            )
            ->andReturn(collect([
                [
                    'start' => Carbon::parse('2026-06-01 09:30:00'),
                    'end' => Carbon::parse('2026-06-01 10:30:00'),
                ],
            ]));

        $this->app->instance(GoogleCalendarService::class, $service);

        $slots = app(MeetingAvailabilityService::class)
            ->slotsForCertification($certification, $date);

        $times = $slots
            ->map(fn (array $slot) => $slot['slot_start']->format('H:i'))
            ->all();

        // 09:00-10:00、10:00-11:00 は予定と重複するため除外
        $this->assertSame(['11:00'], $times);
    }

    public function test_keeps_slots_when_google_calendar_busy_period_does_not_overlap(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $this->attachCoach($certification, $coach);

        GoogleCredential::factory()
            ->for($coach, 'user')
            ->create();

        $date = Carbon::parse('2026-06-01');

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '12:00:00')
            ->create();

        $service = Mockery::mock(GoogleCalendarService::class);

        $service->shouldReceive('busyPeriods')
            ->once()
            ->andReturn(collect([
                [
                    // 09:00-10:00 の面談枠と終了・開始時刻が一致
                    'start' => Carbon::parse('2026-06-01 10:00:00'),
                    'end' => Carbon::parse('2026-06-01 11:00:00'),
                ],
            ]));

        $this->app->instance(GoogleCalendarService::class, $service);

        $slots = app(MeetingAvailabilityService::class)
            ->slotsForCertification($certification, $date);

        $times = $slots
            ->map(fn (array $slot) => $slot['slot_start']->format('H:i'))
            ->all();

        // 09:00-10:00 と 11:00-12:00 は残る
        $this->assertSame(['09:00', '11:00'], $times);
    }

    public function test_keeps_slots_when_google_calendar_api_fails(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $this->attachCoach($certification, $coach);

        GoogleCredential::factory()
            ->for($coach, 'user')
            ->create();

        $date = Carbon::parse('2026-06-01');

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '12:00:00')
            ->create();

        $service = Mockery::mock(GoogleCalendarService::class);

        $service->shouldReceive('busyPeriods')
            ->once()
            ->andThrow(new \RuntimeException('Google API error'));

        $this->app->instance(GoogleCalendarService::class, $service);

        Log::spy();

        $slots = app(MeetingAvailabilityService::class)
            ->slotsForCertification($certification, $date);

        // Google API の失敗時も LMS 側の空き枠を返す
        $this->assertCount(3, $slots);

        $this->assertSame(
            ['09:00', '10:00', '11:00'],
            $slots->map(
                fn (array $slot) => $slot['slot_start']->format('H:i')
            )->all()
        );

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'Google Calendar の予定取得に失敗しました。',
                Mockery::on(
                    fn (array $context) => $context['coach_id'] === $coach->id
                        && $context['exception'] === 'Google API error'
                )
            );
    }

    public function test_skips_google_calendar_api_when_coach_is_not_connected(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $this->attachCoach($certification, $coach);

        $date = Carbon::parse('2026-06-01');

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '12:00:00')
            ->create();

        $service = Mockery::mock(GoogleCalendarService::class);

        $service->shouldNotReceive('busyPeriods');

        $this->app->instance(GoogleCalendarService::class, $service);

        $slots = app(MeetingAvailabilityService::class)
            ->slotsForCertification($certification, $date);

        $this->assertCount(3, $slots);

        $this->assertSame(
            ['09:00', '10:00', '11:00'],
            $slots->map(
                fn (array $slot) => $slot['slot_start']->format('H:i')
            )->all()
        );
    }
}
