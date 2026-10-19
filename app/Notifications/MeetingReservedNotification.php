<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\UserRole;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 面談予約通知。
 *
 * 面談が予約された際に、担当コーチへ
 * アプリ内通知およびメール通知を送信する。
 */
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
        $url = $notifiable->role === UserRole::Coach
            ? route('coach.meetings.index')
            : route('meetings.index');

        return (new MailMessage)
            ->subject('【Certify LMS】面談予約のお知らせ')
            ->greeting('Certify LMS をご利用の皆様へ')
            ->line('面談が予約されました。')
            ->action('業務画面を開く', $url)
            ->line('心当たりがない場合は、このメールを破棄してください。')
            ->salutation('Certify LMS 運営チーム');
    }
}
