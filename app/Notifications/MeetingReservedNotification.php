<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingReservedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => 'meeting_reserved',
            'title' => '面談が予約されました',
            'message' => '面談が予約されました。',
            'body' => '面談が予約されました。',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('【Certify LMS】面談予約のお知らせ')
            ->greeting('Certify LMS をご利用の皆様へ')
            ->line('面談が予約されました。')
            ->action(
                '業務画面を開く',
                route('meetings.index')
            )
            ->line('心当たりがない場合は、このメールを破棄してください。')
            ->salutation('Certify LMS 運営チーム');
    }
}
