<?php

declare(strict_types=1);

namespace App\UseCases\AiChat\Conversation;

use App\Models\AiChatConversation;

/**
 * AI 相談の会話詳細と関連情報を取得するユースケース。
 *
 * Section・資格情報および会話に属するメッセージを取得する。
 */
final class ShowAction
{
    public function __invoke(AiChatConversation $conversation): AiChatConversation
    {
        return $conversation->load([
            'section.chapter.part.certification',
            'messages' => fn ($query) => $query->orderBy('created_at'),
        ]);
    }
}
