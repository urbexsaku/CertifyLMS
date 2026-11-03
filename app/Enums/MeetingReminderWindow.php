<?php

declare(strict_types=1);

namespace App\Enums;

enum MeetingReminderWindow: string
{
    case Eve = 'eve';
    case OneHourBefore = 'one_hour_before';

    public function label(): string
    {
        return match ($this) {
            self::Eve => '面談前日リマインド',
            self::OneHourBefore => '面談1時間前リマインド',
        };
    }
}
