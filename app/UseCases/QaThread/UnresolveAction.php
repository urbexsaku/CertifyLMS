<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;

/**
 * 質問スレッドを未解決に戻すユースケース
 */
final class UnresolveAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        if ($thread->status === QaThreadStatus::Open) {
            return $thread->fresh(['user', 'certification']);
        }

        $thread->update([
            'status' => QaThreadStatus::Open->value,
            'resolved_at' => null,
        ]);

        return $thread->fresh(['user', 'certification']);
    }
}
