<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\UserRole;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 面談キャンセル通知。
 *
 * 面談がキャンセルされた際に、相手方へ
 * アプリ内通知およびメール通知を送信する。
 */
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
        $url = $notifiable->role === UserRole::Coach
            ? route('coach.meetings.index')
            : route('meetings.index');

        return (new MailMessage)
            ->subject('【Certify LMS】面談キャンセルのお知らせ')
            ->greeting('Certify LMS をご利用の皆様へ')
            ->line('予定されていた面談がキャンセルされました。')
            ->action('業務画面を開く', $url)
            ->line('心当たりがない場合は、このメールを破棄してください。')
            ->salutation('Certify LMS 運営チーム');
    }
}
