<?php

declare(strict_types=1);

namespace App\UseCases\Chat;

use App\Events\ChatMessageSent;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use App\UseCases\Notification\SendNotificationAction;
use Illuminate\Support\Facades\DB;

/**
 * ChatRoom にメッセージを INSERT し、送信者の既読時刻を更新したうえで
 * コミット後に Broadcast と受信者への通知を発行する Action。
 *
 * - INSERT 後、ChatMessage::booted() が `chat_rooms.last_message_at` を denormalize 更新する
 * - 送信者自身の `ChatMember.last_read_at = now()` を UPDATE(自分のメッセージは未読としてカウントしない)
 * - 通信失敗が DB 整合性に波及しないよう Pusher Broadcast は `DB::afterCommit()` で送る
 * - 担当コーチ未割当の判定は Controller 側で実施済(`CertificationCoachNotAssignedForChatException` 振り分け)
 */
final class StoreMessageAction
{
    public function __construct(
        private readonly SendNotificationAction $sendNotificationAction,
    ) {
    }
    
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(User $sender, ChatRoom $room, array $validated): ChatMessage
    {
        return DB::transaction(function () use ($sender, $room, $validated) {
            $message = ChatMessage::create([
                'chat_room_id' => $room->id,
                'sender_user_id' => $sender->id,
                'body' => $validated['body'],
            ]);

            ChatMember::query()
                ->where('chat_room_id', $room->id)
                ->where('user_id', $sender->id)
                ->update(['last_read_at' => now()]);

            DB::afterCommit(function () use ($message, $room, $sender): void {
                broadcast(new ChatMessageSent($message->load('sender')))->toOthers();
            
                ChatMember::query()
                    ->forRoom($room)
                    ->where('user_id', '!=', $sender->id)
                    ->with('user')
                    ->get()
                    ->each(function (ChatMember $member): void {
                        if ($member->user !== null) {
                            ($this->sendNotificationAction)(
                                $member->user,
                                new ChatMessageReceivedNotification(),
                            );
                        }
                    });
                });

            return $message;
        });
    }
}
