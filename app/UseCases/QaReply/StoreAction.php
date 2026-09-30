<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 質問掲示板回答を新規作成するユースケース。
 */
final class StoreAction
{
    public function __invoke(QaThread $thread, User $auth, array $validated): QaReply
    {
        return DB::transaction(fn () => QaReply::create([
            'qa_thread_id' => $thread->id,
            'user_id' => $auth->id,
            'body' => $validated['body'],
        ]));
    }
}
