<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingCanceledNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => 'meeting_canceled',
            'title' => '面談がキャンセルされました',
            'message' => '予定されていた面談がキャンセルされました。',
            'body' => '予定されていた面談がキャンセルされました。',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('【Certify LMS】面談キャンセルのお知らせ')
            ->greeting('Certify LMS をご利用の皆様へ')
            ->line('予定されていた面談がキャンセルされました。')
            ->line('Certify LMS にログインして詳細をご確認ください。')
            ->line('心当たりがない場合は、このメールを破棄してください。')
            ->salutation('Certify LMS 運営チーム');
    }
}
