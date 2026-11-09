<?php

declare(strict_types=1);

namespace App\UseCases\AiChat\Conversation;

use App\Models\AiChatConversation;
use App\Models\User;

/**
 * 受講生本人の AI 相談履歴から最新の会話を取得する Action。
 */
final class IndexAction
{
    public function __invoke(User $student): ?AiChatConversation
    {
        return AiChatConversation::query()
            ->where('user_id', $student->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->first();
    }
}
