<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChatMessageReceivedNotification extends Notification
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
            'notification_type' => 'chat_message_received',
            'route' => 'chat.show',
            'route_params' => [
                'room' => $this->targetId,
            ],
            'title' => '新しいチャットメッセージがあります',
            'message' => '新しいチャットメッセージを受信しました。',
            'body' => '新しいチャットメッセージを受信しました。',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('【Certify LMS】チャット受信のお知らせ')
            ->greeting('Certify LMS をご利用の皆様へ')
            ->line('新しいチャットメッセージを受信しました。')
            ->line('Certify LMS にログインして内容をご確認ください。')
            ->line('心当たりがない場合は、このメールを破棄してください。')
            ->salutation('Certify LMS 運営チーム');
    }
}
