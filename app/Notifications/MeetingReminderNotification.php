<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\MeetingReminderWindow;
use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 面談リマインダー通知。
 *
 * 面談前日または1時間前に、受講生と担当コーチへ
 * アプリ内通知およびメール通知を送信する。
 */
class MeetingReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Meeting $meeting,
        private readonly MeetingReminderWindow $window,
    ) {
        $this->meeting->loadMissing('enrollment.certification');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        $title = $this->window->label();

        $certificationName = $this->meeting->enrollment?->certification?->name
            ?? '資格名未設定';

        $topic = $this->meeting->topic ?: '面談内容未設定';
        $topic = mb_strimwidth($topic, 0, 40, '…');

        $scheduledAt = $this->meeting->scheduled_at->format('n/j H:i');

        $message = implode(' / ', [
            $certificationName,
            $topic,
            $scheduledAt,
        ]);

        return [
            'notification_type' => 'meeting_reminder',
            'meeting_id' => $this->meeting->id,
            'window' => $this->window->value,
            'route_params' => ['meeting' => $this->meeting->id],
            'title' => $title,
            'message' => $message,
            'body' => $message,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $certificationName = $this->meeting->enrollment?->certification?->name
        ?? '資格名未設定';

        $topic = $this->meeting->topic ?: '面談内容未設定';

        $scheduledAt = $this->meeting->scheduled_at
            ->format('Y年n月j日 H:i');

        $mail = (new MailMessage)
            ->subject('【Certify LMS】'.$this->window->label())
            ->greeting('Certify LMS をご利用の皆様へ')
            ->line($this->window->label().'です。')
            ->line('資格：'.$certificationName)
            ->line('面談内容：'.$topic)
            ->line('面談日時：'.$scheduledAt);

        if ($this->meeting->meeting_url_snapshot !== null) {
            $mail->action(
                '面談に参加する',
                $this->meeting->meeting_url_snapshot
            );
        }

        return $mail->salutation('Certify LMS 運営チーム');
    }
}
