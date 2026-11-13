<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingOutOfAvailabilityException;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 担当コーチ集合の面談可能時間枠を 60 分単位で展開し、空きスロットを集計する Service。
 *
 * 受講生の予約画面が「該当資格の担当コーチ全員の有効枠 Union」を 1 日単位で取得し、
 * 既存予約済時刻と Google Calendar の予定を除外して各スロットの
 * 「予約可能なコーチ数」を返す。受講生にコーチ個別は提示せず、
 * 予約確定時にコーチを自動割当する。
 */
final class MeetingAvailabilityService
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    /**
     * 指定 Certification の担当コーチ集合について、指定日 1 日分の 60 分単位空きスロットを返す。
     *
     * @return Collection<int, array{slot_start: Carbon, slot_end: Carbon, available_coach_count: int}>
     */
    public function slotsForCertification(Certification $certification, Carbon $date): Collection
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $dayStart->copy()->addDay();
        $dayOfWeek = $date->dayOfWeek;

        // Google Calendar 連携情報も取得し、コーチごとの追加クエリを防ぐ。
        $coaches = $certification->coaches()
            ->with('googleCredential')
            ->get();

        if ($coaches->isEmpty()) {
            return collect();
        }

        $coachIds = $coaches->pluck('id')->all();

        $availabilities = CoachAvailability::query()
            ->whereIn('coach_id', $coachIds)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get();

        $existingMeetings = Meeting::query()
            ->whereIn('coach_id', $coachIds)
            ->whereBetween('scheduled_at', [$dayStart, $dayEnd->copy()->subSecond()])
            ->whereIn('status', [MeetingStatus::Reserved->value, MeetingStatus::Completed->value])
            ->get(['coach_id', 'scheduled_at']);

        // 予約済スロットを (coach_id => Set<H:i>) で索引化
        $bookedByCoach = $existingMeetings
            ->groupBy('coach_id')
            ->map(fn ($rows) => $rows->map(fn (Meeting $meeting) => $meeting->scheduled_at->format('H:i'))->all());

        // Google Calendar の予定時間帯をコーチごとに取得する。
        // API エラー時はログを残し、LMS 側の空き枠取得は継続する。
        $busyPeriodsByCoach = [];

        foreach ($coaches as $coach) {
            if ($coach->googleCredential === null) {
                continue;
            }

            try {
                $busyPeriodsByCoach[$coach->id] = $this->googleCalendarService->busyPeriods(
                    $coach,
                    $dayStart,
                    $dayEnd,
                )->all();
            } catch (Throwable $exception) {
                Log::warning('Google Calendar の予定取得に失敗しました。', [
                    'coach_id' => $coach->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        /** @var array<string, int> $slotCounts スロット開始時刻(H:i) → available coach 数 */
        $slotCounts = [];

        foreach ($availabilities as $availability) {
            $slot = Carbon::parse($date->format('Y-m-d').' '.$availability->start_time);
            $end = Carbon::parse($date->format('Y-m-d').' '.$availability->end_time);

            while ($slot->copy()->addHour() <= $end) {
                $slotKey = $slot->format('H:i');
                $slotEnd = $slot->copy()->addHour();
                $coachId = $availability->coach_id;
                $booked = $bookedByCoach[$coachId] ?? [];

                $hasExistingMeeting = in_array($slotKey, $booked, true);

                $hasGoogleCalendarConflict = collect($busyPeriodsByCoach[$coachId] ?? [])
                    ->contains(fn (array $period) => (
                        $slot->lt($period['end'])
                        && $slotEnd->gt($period['start'])
                    ));

                if (! $hasExistingMeeting && ! $hasGoogleCalendarConflict) {
                    $slotCounts[$slotKey] = ($slotCounts[$slotKey] ?? 0) + 1;
                }

                $slot->addHour();
            }
        }

        ksort($slotCounts);

        return collect($slotCounts)->map(function (int $count, string $time) use ($date) {
            $start = Carbon::parse($date->format('Y-m-d').' '.$time);

            return [
                'slot_start' => $start,
                'slot_end' => $start->copy()->addHour(),
                'available_coach_count' => $count,
            ];
        })->values();
    }

    /**
     * 指定 scheduled_at が certification 担当コーチ集合の有効枠内かを検証する。
     * 枠外なら MeetingOutOfAvailabilityException を throw する。
     *
     * @throws MeetingOutOfAvailabilityException
     */
    public function validateSlot(Certification $certification, Carbon $scheduledAt): void
    {
        $slots = $this->slotsForCertification($certification, $scheduledAt->copy()->startOfDay());

        $matched = $slots->contains(
            fn (array $slot) => $slot['slot_start']->equalTo($scheduledAt) && $slot['available_coach_count'] > 0,
        );

        if (! $matched) {
            throw new MeetingOutOfAvailabilityException;
        }
    }
}
