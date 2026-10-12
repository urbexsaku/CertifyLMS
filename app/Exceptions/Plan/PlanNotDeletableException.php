<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 削除条件を満たさないプランを削除しようとした際の例外。
 * HTTP 409 Conflict にマップされる。
 */
class PlanNotDeletableException extends ConflictHttpException
{
    public static function forInvalidStatus(): self
    {
        return new self('公開中またはアーカイブ済みのプランは削除できません。');
    }

    public static function forStudentsAttached(): self
    {
        return new self('受講生が紐づいているプランは削除できません。');
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
