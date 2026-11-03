<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminAnnouncementNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Announcement $announcement,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => 'admin_announcement',
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'body' => $this->announcement->body,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('【Certify LMS】'.$this->announcement->title)
            ->greeting('管理者からのお知らせ')
            ->line($this->announcement->body)
            ->action(
                'お知らせを開く',
                route('notifications.show', [
                    'notification' => $this->id,
                ])
            )
            ->line('心当たりがない場合は、このメールを破棄してください。')
            ->salutation('Certify LMS 運営チーム');
    }
}
