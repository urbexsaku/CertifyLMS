<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 公開中の面談パックを削除しようとした際の例外。
 * HTTP 409 Conflict にマップされる。
 */
class PlanNotDeletableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(
            '下書き状態で受講生が紐づいていないプランのみ削除できます。',
            $previous,
        );
    }
}
