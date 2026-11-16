<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invitation;
use App\Services\InvitationTokenService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 招待メール。
 *
 * 招待先ユーザーにアカウント登録用のメールを送信する。
 * キューを利用して非同期で送信し、一時的な送信失敗時は再試行する。
 */
class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * 最大試行回数。
     */
    public int $tries = 3;

    public function __construct(public Invitation $invitation) {}

    /**
     * リトライ時の待機秒数。
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->invitation->email],
            subject: 'Certify LMS への招待',
        );
    }

    public function content(): Content
    {
        $url = app(InvitationTokenService::class)->generateUrl($this->invitation);

        return new Content(
            markdown: 'emails.invitation',
            with: [
                'invitation' => $this->invitation,
                'invitedBy' => $this->invitation->invitedBy,
                'roleLabel' => $this->invitation->role->label(),
                'expiresAt' => $this->invitation->expires_at,
                'url' => $url,
            ],
        );
    }
}
