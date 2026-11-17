<?php

declare(strict_types=1);

namespace App\UseCases\AiChat\Conversation;

use App\Models\AiChatConversation;

/**
 * AI 相談の会話タイトルを更新するユースケース。
 *
 * 手動編集日時を記録し、AI による自動タイトル生成で上書きされないようにする。
 */
final class UpdateAction
{
    /**
     * @param array{title: string} $validated
     */
    public function __invoke(
        AiChatConversation $conversation,
        array $validated
    ): AiChatConversation {
        $conversation->update([
            'title' => $validated['title'],
            'title_manually_edited_at' => now(),
        ]);

        return $conversation->refresh();
    }
}
