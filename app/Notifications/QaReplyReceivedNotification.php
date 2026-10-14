<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QaReplyReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int|string $targetId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => 'qa_reply_received',
            'route' => 'qa-board.show',
            'route_params' => [
                'thread' => $this->targetId,
            ],
            'title' => 'Q&Aに回答がありました',
            'message' => '投稿したQ&Aに回答がありました。',
            'body' => '投稿したQ&Aに回答がありました。',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('【Certify LMS】Q&A返信のお知らせ')
            ->greeting('Certify LMS をご利用の皆様へ')
            ->line('投稿したQ&Aに回答がありました。')
            ->line('Certify LMS にログインして回答内容をご確認ください。')
            ->line('心当たりがない場合は、このメールを破棄してください。')
            ->salutation('Certify LMS 運営チーム');
    }
}
