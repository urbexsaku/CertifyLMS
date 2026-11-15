<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Enums\MeetingReminderWindow;
use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;

/**
 * 面談リマインダーの対象を抽出し、受講生とコーチに通知する Action。
 *
 * 指定された通知タイミングに該当する予約済み面談を抽出し、
 * 通知済みかどうかを確認した上で、通知対象者にリマインダーを送信する。
 *
 * 通知の重複を防ぐため、面談と通知タイミングの組み合わせでロックを取得する。
 */
final class SendMeetingRemindersAction
{
    /**
     * 指定された通知タイミングに該当する面談を取得し、参加者へ通知する。
     *
     * @param MeetingReminderWindow $window 通知タイミング
     */
    public function __invoke(MeetingReminderWindow $window): void
    {
        $this->targetMeetings($window)
            ->with(['student', 'coach'])
            ->chunkById(100, function ($meetings) use ($window): void {
                foreach ($meetings as $meeting) {
                    $this->notifyParticipants($meeting, $window);
                }
            });
    }

    /**
     * 指定された通知タイミングに該当する予約済み面談を取得する。
     *
     * @param MeetingReminderWindow $window 通知タイミング
     *
     * @return Builder<Meeting>
     */
    private function targetMeetings(MeetingReminderWindow $window): Builder
    {
        $now = now();

        [$from, $until] = match ($window) {
            MeetingReminderWindow::Eve => [
                $now->copy()->addDay()->startOfDay(),
                $now->copy()->addDays(2)->startOfDay(),
            ],

            MeetingReminderWindow::OneHourBefore => [
                $now->copy()->addMinutes(55),
                $now->copy()->addMinutes(65),
            ],
        };

        $query = Meeting::query()
            ->where('status', MeetingStatus::Reserved->value)
            ->where('scheduled_at', '>=', $from);

        if ($window === MeetingReminderWindow::OneHourBefore) {
            return $query->where('scheduled_at', '<=', $until);
        }

        return $query->where('scheduled_at', '<', $until);
    }

    /**
     * 面談の参加者にリマインダーを送信する。
     *
     * 同一面談・同一通知タイミングでの重複送信を防止する。
     *
     * @param Meeting $meeting 対象の面談
     * @param MeetingReminderWindow $window 通知タイミング
     */
    private function notifyParticipants(Meeting $meeting, MeetingReminderWindow $window): void
    {
        $lock = Cache::lock(
            "meeting-reminder:{$meeting->id}:{$window->value}",
            600
        );

        $lock->get(function () use ($meeting, $window): void {
            if ($this->alreadyDispatched($meeting, $window)) {
                return;
            }

            foreach ([$meeting->student, $meeting->coach] as $user) {
                if ($user === null) {
                    continue;
                }

                if (! $this->isEligibleRecipient($user)) {
                    continue;
                }

                $user->notify(new MeetingReminderNotification($meeting, $window));
            }
        });
    }

    /**
     * 指定された面談・通知タイミングのリマインダーが送信済みか判定する。
     *
     * @param Meeting $meeting 対象の面談
     * @param MeetingReminderWindow $window 通知タイミング
     *
     * @return bool
     */
    private function alreadyDispatched(Meeting $meeting, MeetingReminderWindow $window): bool
    {
        return DatabaseNotification::query()
            ->where('type', MeetingReminderNotification::class)
            ->where('data->meeting_id', $meeting->id)
            ->where('data->window', $window->value)
            ->exists();
    }

    /**
     * リマインダーの送信対象となるユーザーか判定する。
     *
     * @param User $user 判定対象のユーザー
     *
     * @return bool
     */
    private function isEligibleRecipient(User $user): bool
    {
        return in_array($user->role, [UserRole::Student, UserRole::Coach], true)
            && $user->status === UserStatus::InProgress;
    }
}
