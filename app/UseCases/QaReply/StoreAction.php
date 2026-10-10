<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
use App\UseCases\Notification\SendNotificationAction;
use Illuminate\Support\Facades\DB;

/**
 * 質問掲示板回答を新規作成するユースケース。
 */
final class StoreAction
{
     /**
     * @param array{body: string} $validated
     */
    public function __construct(
        private readonly SendNotificationAction $sendNotificationAction,
    ) {
    }

    public function __invoke(QaThread $thread, User $auth, array $validated): QaReply
    {
        $reply = DB::transaction(fn () => QaReply::create([
            'qa_thread_id' => $thread->id,
            'user_id' => $auth->id,
            'body' => $validated['body'],
        ]));

        $questioner = $thread->user;

        if ($questioner !== null && $questioner->id !== $auth->id) {
            ($this->sendNotificationAction)(
                $questioner,
                new QaReplyReceivedNotification(),
            );
        }

        return $reply;
    }
}
