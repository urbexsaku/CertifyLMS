<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use App\Notifications\MeetingCanceledNotification;
use App\Notifications\MeetingReservedNotification;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class NotificationMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_notification_email_uses_expected_subject_and_url(): void
    {
        // Arrange
        $user = User::factory()->create();
        $targetId = 123;
        $notification = new ChatMessageReceivedNotification($targetId);

        // Act
        $mail = $notification->toMail($user);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('【Certify LMS】チャット受信のお知らせ', $mail->subject);

        $this->assertSame(
            route('chat.show', ['room' => $targetId]),
            $mail->actionUrl
        );
    }

    public function test_qa_reply_notification_email_uses_expected_subject_and_url(): void
    {
        // Arrange
        $user = User::factory()->create();
        $targetId = 123;
        $notification = new QaReplyReceivedNotification($targetId);

        // Act
        $mail = $notification->toMail($user);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('【Certify LMS】Q&A返信のお知らせ', $mail->subject);

        $this->assertSame(
            route('qa-board.show', ['thread' => $targetId]),
            $mail->actionUrl
        );
    }

    public function test_meeting_reserved_notification_email_uses_expected_subject_and_url(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $notification = new MeetingReservedNotification;

        // Act
        $mail = $notification->toMail($coach);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('【Certify LMS】面談予約のお知らせ', $mail->subject);

        $this->assertSame(route('coach.meetings.index'), $mail->actionUrl);
    }

    public function test_meeting_canceled_notification_email_uses_expected_student_or_coach_url(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $notification = new MeetingCanceledNotification;

        // Act
        $coachMail = $notification->toMail($coach);
        $studentMail = $notification->toMail($student);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $coachMail);
        $this->assertSame('【Certify LMS】面談キャンセルのお知らせ', $coachMail->subject);

        $this->assertInstanceOf(MailMessage::class, $studentMail);
        $this->assertSame('【Certify LMS】面談キャンセルのお知らせ', $studentMail->subject);

        $this->assertSame(route('coach.meetings.index'), $coachMail->actionUrl);
        $this->assertSame(route('meetings.index'), $studentMail->actionUrl);
    }
}
