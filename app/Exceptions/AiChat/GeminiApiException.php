<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use RuntimeException;
use Throwable;

/**
 * Gemini APIの通信・応答エラーを表す例外。
 */
final class GeminiApiException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $upstreamStatus = null,
        ?Throwable $previous = null,
        private readonly bool $configurationError = false,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function upstreamStatus(): ?int
    {
        return $this->upstreamStatus;
    }

    public function isConfigurationError(): bool
    {
        return $this->configurationError;
    }
}
