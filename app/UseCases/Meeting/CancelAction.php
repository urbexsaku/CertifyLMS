<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use App\Services\GoogleCalendarService;
use App\UseCases\MeetingQuota\RefundQuotaAction;
use App\UseCases\Notification\SendNotificationAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 当事者（受講生またはコーチ）による面談キャンセルを処理するユースケース。
 *
 * reserved かつ開始前のみキャンセル可。消費済みの面談回数1回分を返却する。
 */
final class CancelAction
{
    public function __construct(
        private readonly RefundQuotaAction $refundAction,
        private readonly SendNotificationAction $sendNotificationAction,
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    /**
     * @throws MeetingStatusTransitionException
     * @throws MeetingAlreadyStartedException
     */
    public function __invoke(Meeting $meeting, User $actor): void
    {
        DB::transaction(function () use ($meeting, $actor): void {
            $lockedMeeting = Meeting::query()
                ->with(['coach', 'student'])
                ->whereKey($meeting->id)
                ->lockForUpdate()
                ->first();

            if ($lockedMeeting === null || $lockedMeeting->status !== MeetingStatus::Reserved) {
                throw MeetingStatusTransitionException::forCancel();
            }

            if ($lockedMeeting->scheduled_at->lessThanOrEqualTo(now())) {
                throw new MeetingAlreadyStartedException;
            }

            $lockedMeeting->update([
                'status' => MeetingStatus::Canceled->value,
                'canceled_by_user_id' => $actor->id,
                'canceled_at' => now(),
            ]);

            ($this->refundAction)($lockedMeeting->student, (string) $lockedMeeting->id);

            $recipient = (string) $actor->id === (string) $lockedMeeting->student_id
                ? $lockedMeeting->coach
                : $lockedMeeting->student;

            DB::afterCommit(function () use ($lockedMeeting, $recipient): void {
                try {
                    $this->googleCalendarService->deleteMeetingEvent($lockedMeeting);
                } catch (Throwable $exception) {
                    Log::warning('Google Calendar の面談予定削除に失敗しました。', [
                        'meeting_id' => $lockedMeeting->id,
                        'coach_id' => $lockedMeeting->coach_id,
                        'google_calendar_event_id' => $lockedMeeting->google_calendar_event_id,
                        'exception' => $exception->getMessage(),
                    ]);
                }

                ($this->sendNotificationAction)($recipient, new MeetingCanceledNotification);
            });
        });
    }
}
