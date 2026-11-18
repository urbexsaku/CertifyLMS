<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class AiChatRateLimitExceededException extends HttpException
{
    public function __construct()
    {
        parent::__construct(
            429,
            "本日の利用上限に達しました。\n明日 0:00 以降に再度ご利用ください。",
        );
    }
}
