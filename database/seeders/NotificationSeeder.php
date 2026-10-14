<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\ChatMessage;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use App\Notifications\MeetingCanceledNotification;
use App\Notifications\MeetingReservedNotification;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * アプリ内通知の開発用シーダー。
 *
 * 固定受講生・固定コーチに通知を作成し、通知一覧の表示、
 * 既読・未読、ページネーション、遷移先の確認に利用する。
 *
 * 依存順序:
 * UserSeeder → EnrollmentSeeder → MentoringSeeder
 * → ChatSeeder → QaThreadSeeder → QaReplySeeder → 本 Seeder
 */
final class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        $coach = User::query()
            ->where('email', 'coach@certify-lms.test')
            ->first();

        if ($student === null || $coach === null) {
            $this->command?->warn(
                'NotificationSeeder: 固定受講生または固定コーチが存在しません。先に UserSeeder を実行してください。'
            );

            return;
        }

        $this->seedStudentNotification($student);
        $this->seedCoachNotification($student, $coach);
    }

    private function seedStudentNotification(User $student): void
    {
        $studentChatMessages = ChatMessage::query()
            ->whereHas('chatRoom.members', function ($query) use ($student): void {
                $query->where('user_id', $student->id);
            })
            ->whereHas('sender', function ($query): void {
                $query->where('role', UserRole::Coach->value);
            })
            ->orderBy('created_at')
            ->take(4)
            ->get();

        if ($studentChatMessages->count() < 4) {
            $this->command?->warn(
                'NotificationSeeder: 固定受講生向けのチャットメッセージが不足しています。先に ChatSeeder を実行してください。'
            );

            return;
        }

        $qaThreads = QaThread::query()
            ->where('user_id', $student->id)
            ->whereHas('replies', function ($query) use ($student): void {
                $query->where('user_id', '!=', $student->id);
            })
            ->orderBy('created_at')
            ->take(9)
            ->get();

        if ($qaThreads->count() < 9) {
            $this->command?->warn(
                'NotificationSeeder: 固定受講生向けのQ&A回答が不足しています。先に QaThreadSeeder と QaReplySeeder を実行してください。'
            );

            return;
        }

        $studentNotifications = [];

        foreach ($studentChatMessages as $chatMessage) {
            $studentNotifications[] = [
                'type' => ChatMessageReceivedNotification::class,
                'data' => [
                    'notification_type' => 'chat_message_received',
                    'route' => 'chat.show',
                    'route_params' => ['room' => $chatMessage->chat_room_id],
                    'title' => '新しいチャットメッセージがあります',
                    'message' => '新しいチャットメッセージを受信しました。',
                    'body' => '新しいチャットメッセージを受信しました。',
                ],
            ];
        }

        foreach ($qaThreads as $thread) {
            $studentNotifications[] = [
                'type' => QaReplyReceivedNotification::class,
                'data' => [
                    'notification_type' => 'qa_reply_received',
                    'route' => 'qa-board.show',
                    'route_params' => ['thread' => $thread->id],
                    'title' => 'Q&Aに回答がありました',
                    'message' => '投稿したQ&Aに回答がありました。',
                    'body' => '投稿したQ&Aに回答がありました。',
                ],
            ];
        }

        for ($i = 0; $i < 8; $i++) {
            $studentNotifications[] = [
                'type' => MeetingCanceledNotification::class,
                'data' => [
                    'notification_type' => 'meeting_canceled',
                    'title' => '面談がキャンセルされました',
                    'message' => '予定されていた面談がキャンセルされました。',
                    'body' => '予定されていた面談がキャンセルされました。',
                ],
            ];
        }

        $this->insertNotifications($student, $studentNotifications);
    }

    private function seedCoachNotification(User $student, User $coach): void
    {
        $coachChatMessages = ChatMessage::query()
            ->where('sender_user_id', $student->id)
            ->whereHas('chatRoom.members', function ($query) use ($coach): void {
                $query->where('user_id', $coach->id);
            })
            ->orderBy('created_at')
            ->take(2)
            ->get();

        if ($coachChatMessages->count() < 2) {
            $this->command?->warn(
                'NotificationSeeder: 固定コーチ向けのチャットメッセージが不足しています。先に ChatSeeder を実行してください。'
            );

            return;
        }

        $coachNotifications = [];

        foreach ($coachChatMessages as $chatMessage) {
            $coachNotifications[] = [
                'type' => ChatMessageReceivedNotification::class,
                'data' => [
                    'notification_type' => 'chat_message_received',
                    'route' => 'chat.show',
                    'route_params' => ['room' => $chatMessage->chat_room_id],
                    'title' => '新しいチャットメッセージがあります',
                    'message' => '新しいチャットメッセージを受信しました。',
                    'body' => '新しいチャットメッセージを受信しました。',
                ],
            ];
        }

        for ($i = 0; $i < 10; $i++) {
            $coachNotifications[] = [
                'type' => MeetingReservedNotification::class,
                'data' => [
                    'notification_type' => 'meeting_reserved',
                    'title' => '面談が予約されました',
                    'message' => '面談が予約されました。',
                    'body' => '面談が予約されました。',
                ],
            ];
        }

        for ($i = 0; $i < 9; $i++) {
            $coachNotifications[] = [
                'type' => MeetingCanceledNotification::class,
                'data' => [
                    'notification_type' => 'meeting_canceled',
                    'title' => '面談がキャンセルされました',
                    'message' => '予定されていた面談がキャンセルされました。',
                    'body' => '予定されていた面談がキャンセルされました。',
                ],
            ];
        }

        $this->insertNotifications($coach, $coachNotifications);
    }

    private function insertNotifications(User $user, array $notifications): void
    {
        $baseTime = now();

        foreach ($notifications as $index => $notification) {
            $createdAt = $baseTime->copy()->subMinutes($index + 1);

            DB::table('notifications')->insert([
                'id' => (string) Str::ulid(),
                'type' => $notification['type'],
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode(
                    $notification['data'],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                ),
                'read_at' => $index < 8
                    ? $createdAt->copy()->addSeconds(30)
                    : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
