<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MeetingReminderWindow;
use App\UseCases\Notification\SendMeetingRemindersAction;
use Illuminate\Console\Command;

class SendMeetingRemindersCommand extends Command
{
    protected $signature = 'notifications:send-meeting-reminders {--window= : Reminder window (eve or one_hour_before)}';

    protected $description = '面談のリマインドを配信する。';

    public function handle(SendMeetingRemindersAction $action): int
    {
        $windowValue = $this->option('window');

        $window = is_string($windowValue)
            ? MeetingReminderWindow::tryFrom($windowValue)
            : null;

        if ($window === null) {
            $this->error('通知タイミングが不正です。eve または one_hour_before を指定してください。');

            return self::FAILURE;
        }

        $action($window);

        return self::SUCCESS;
    }
}
