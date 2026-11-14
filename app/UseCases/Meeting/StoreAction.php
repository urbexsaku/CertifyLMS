<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingNoAvailableCoachException;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReservedNotification;
use App\Services\CoachMeetingLoadService;
use App\Services\GoogleCalendarService;
use App\Services\MeetingAvailabilityService;
use App\Services\MeetingQuotaService;
use App\UseCases\MeetingQuota\ConsumeQuotaAction;
use App\UseCases\Notification\SendNotificationAction;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 受講生の予約申請を作成するユースケース。
 *
 * 残面談回数を確認し、空き枠から過去実績最少のコーチを自動割当して reserved で確定する。
 * 同時刻の予約競合は UniqueConstraintViolationException として検知し、面談予約不可の例外へ変換する。
 */
final class StoreAction
{
    public function __construct(
        private readonly MeetingAvailabilityService $availabilityService,
        private readonly GoogleCalendarService $googleCalendarService,
        private readonly CoachMeetingLoadService $coachLoadService,
        private readonly MeetingQuotaService $quotaService,
        private readonly ConsumeQuotaAction $consumeAction,
        private readonly SendNotificationAction $sendNotificationAction,
    ) {}

    /**
     * @param array{scheduled_at: string, topic: string} $validated
     */
    public function __invoke(
        Enrollment $enrollment,
        array $validated,
    ): Meeting {
        $scheduledAt = Carbon::parse($validated['scheduled_at']);
        $topic = $validated['topic'];
        $student = $enrollment->user;

        return DB::transaction(function () use (
            $enrollment,
            $student,
            $scheduledAt,
            $topic,
        ) {
            if ($this->quotaService->remaining($student) < 1) {
                throw new InsufficientMeetingQuotaException;
            }

            $this->availabilityService->validateSlot($enrollment->certification, $scheduledAt);

            $candidates = $this->findAvailableCoaches($enrollment->certification, $scheduledAt);
            if ($candidates->isEmpty()) {
                throw new MeetingNoAvailableCoachException;
            }

            $coach = $this->coachLoadService->leastLoadedCoach($candidates);

            try {
                $meeting = Meeting::create([
                    'enrollment_id' => $enrollment->id,
                    'coach_id' => $coach->id,
                    'student_id' => $student->id,
                    'scheduled_at' => $scheduledAt,
                    'status' => MeetingStatus::Reserved->value,
                    'topic' => $topic,
                    'meeting_url_snapshot' => $coach->meeting_url,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                // 同時刻に他受講生が先行予約した race condition: UNIQUE(coach_id, scheduled_at) で弾かれた
                throw new MeetingNoAvailableCoachException($e);
            }

            $transaction = ($this->consumeAction)($student, $meeting->id);

            $meeting->update(['meeting_quota_transaction_id' => $transaction->id]);

            $meeting = $meeting->fresh(['coach.googleCredential']);

            DB::afterCommit(function () use ($meeting): void {
                // Google Calendar 未連携の場合はイベント登録をスキップする。
                if ($meeting->coach->googleCredential !== null) {
                    try {
                        $this->googleCalendarService->createMeetingEvent($meeting);
                    } catch (Throwable $exception) {
                        Log::warning('Google Calendar への面談予定登録に失敗しました。', [
                            'meeting_id' => $meeting->id,
                            'coach_id' => $meeting->coach_id,
                            'exception' => $exception->getMessage(),
                        ]);
                    }
                }

                ($this->sendNotificationAction)($meeting->coach, new MeetingReservedNotification);
            });

            return $meeting;
        });
    }

    /**
     * 担当コーチ集合のうち、以下の条件を満たすコーチ集合を返す。
     *
     * (1) 当該時刻に有効な availability 枠がある。
     * (2) 当該時刻に reserved / completed の Meeting を持たない。
     * (3) Google Calendar に当該面談時間と重複する予定がない。
     *
     * @return Collection<int, User>
     */
    private function findAvailableCoaches(Certification $certification, Carbon $scheduledAt): Collection
    {
        $time = $scheduledAt->format('H:i:s');
        $meetingEnd = $scheduledAt->copy()->addHour();

        $candidates = $certification->coaches()
            ->with('googleCredential')
            ->whereHas('coachAvailabilities', function ($q) use ($scheduledAt, $time) {
                $q->where('day_of_week', $scheduledAt->dayOfWeek)
                    ->where('is_active', true)
                    ->where('start_time', '<=', $time)
                    ->where('end_time', '>', $time);
            })
            ->whereDoesntHave('meetingsAsCoach', function ($q) use ($scheduledAt) {
                $q->where('scheduled_at', $scheduledAt)
                    ->whereIn('status', [MeetingStatus::Reserved->value, MeetingStatus::Completed->value]);
            })
            ->get();

        return $candidates
            ->reject(function (User $coach) use ($scheduledAt, $meetingEnd): bool {
                if ($coach->googleCredential === null) {
                    return false;
                }

                try {
                    $busyPeriods = $this->googleCalendarService->busyPeriods(
                        $coach,
                        $scheduledAt,
                        $meetingEnd,
                    );

                    return $busyPeriods->contains(
                        fn (array $period) => (
                            $scheduledAt->lt($period['end'])
                            && $meetingEnd->gt($period['start'])
                        ),
                    );
                } catch (Throwable $exception) {
                    Log::warning('Google Calendar の予定取得に失敗しました。', [
                        'coach_id' => $coach->id,
                        'scheduled_at' => $scheduledAt->toIso8601String(),
                        'exception' => $exception->getMessage(),
                    ]);

                    // API エラーではコーチを候補から除外せず、LMS の予約処理を継続する。
                    return false;
                }
            })
            ->values();
    }
}
