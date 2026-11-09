<?php

declare(strict_types=1);

namespace App\UseCases\AiChat\Conversation;

use App\Models\AiChatConversation;

/**
 * AI 相談の会話を削除するユースケース。
 *
 * 会話に紐づくメッセージの削除は、DB の外部キー制約に従う。
 */
final class DestroyAction
{
    public function __invoke(AiChatConversation $conversation): void
    {
        $conversation->delete();
    }
}
