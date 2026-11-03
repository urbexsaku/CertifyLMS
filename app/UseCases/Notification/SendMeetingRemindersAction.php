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

final class SendMeetingRemindersAction
{
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

    private function alreadyDispatched(Meeting $meeting, MeetingReminderWindow $window): bool
    {
        return DatabaseNotification::query()
            ->where('type', MeetingReminderNotification::class)
            ->where('data->meeting_id', $meeting->id)
            ->where('data->window', $window->value)
            ->exists();
    }

    private function isEligibleRecipient(User $user): bool
    {
        return in_array($user->role, [UserRole::Student, UserRole::Coach], true)
            && $user->status === UserStatus::InProgress;
    }
}
