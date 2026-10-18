<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\UserRole;
use App\Exceptions\QaThread\QaThreadNotDeletableException;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 質問掲示板スレッドを削除するユースケース。
 * Student: 回答があるスレッドを削除不可。
 * Admin: 回答有無にかかわらず削除可。回答がある場合は、先に回答を削除してからスレッドを削除する。
 */
final class DestroyAction
{
    /**
     * @throws QaThreadNotDeletableException
     */
    public function __invoke(User $auth, QaThread $thread): void
    {
        DB::transaction(function () use ($auth, $thread) {
            $thread = QaThread::query()
                ->lockForUpdate()
                ->findOrFail($thread->id);

            if ($auth->role === UserRole::Student && $thread->replies()->exists()) {
                throw new QaThreadNotDeletableException;
            }

            $thread->replies()->delete();
            $thread->delete();
        });
    }
}
