<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;

/**
 * 質問掲示板スレッド詳細を取得するユースケース。
 */
final class ShowAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        return $thread->loadCount('replies')->load([
            'replies' => fn ($query) => $query->with('user')->ordered(),
            'user',
            'certification',
        ]);
    }
}
