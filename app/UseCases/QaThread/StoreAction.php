<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 質問掲示板スレッドを新規作成するユースケース。
 */
final class StoreAction
{
    public function __invoke(User $auth, array $validated): QaThread
    {
        return DB::transaction(fn () => QaThread::create([
            'user_id' => $auth->id,
            'certification_id' => $validated['certification_id'],
            'title' => $validated['title'],
            'body' => $validated['body'],
        ]));
    }
}
