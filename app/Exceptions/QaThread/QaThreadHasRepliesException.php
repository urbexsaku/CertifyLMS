<?php

declare(strict_types=1);

namespace App\Exceptions\QaThread;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 回答が存在する質問スレッドをStudentが削除しようとした際の例外。
 * HTTP 409 Conflict にマップされる。
 */
final class QaThreadHasRepliesException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(
            '回答が存在する質問スレッドは削除できません。',
            $previous,
        );
    }
}
