<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AiChatMessage\StoreRequest;
use App\Models\AiChatConversation;
use App\UseCases\AiChat\Message\StoreAction;
use Illuminate\Http\JsonResponse;

/**
 * AI 相談のメッセージ送信を行うコントローラー。
 *
 * 受講生のメッセージ送信と Gemini による回答取得を扱う。
 */
class AiChatMessageController extends Controller
{
    public function store(
        StoreRequest $request,
        AiChatConversation $conversation,
        StoreAction $action,
    ): JsonResponse {
        $result = $action(
            $conversation,
            $request->validated()['content'],
        );

        return response()->json([
            'user_message' => $result['user_message'],
            'assistant_message' => $result['assistant_message'],
            'conversation' => $result['conversation'],
        ]);
    }
}
