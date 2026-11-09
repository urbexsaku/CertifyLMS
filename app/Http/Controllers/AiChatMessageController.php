<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AiChatMessage\StoreRequest;
use App\Models\AiChatConversation;
use App\UseCases\AiChat\Message\StoreAction;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * AI 相談のメッセージ送信を行うコントローラー。
 *
 * 受講生のメッセージ送信と Gemini による回答取得を扱う。
 */
class AiChatMessageController extends Controller
{
    public function store(StoreRequest $request, AiChatConversation $conversation, StoreAction $action): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $conversation);

        try {
            $result = $action($conversation, $request->validated()['content']);
        } catch (HttpResponseException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            $redirect = redirect()->route('ai-chat.conversations.show', $conversation);

            return match ($exception->getResponse()->getStatusCode()) {
                429 => $redirect->with(
                    'warning',
                    '本日の利用上限に達したため、質問を送信できませんでした。',
                ),
                502 => $redirect->with(
                    'warning',
                    '質問は保存しましたが、AIから回答を取得できませんでした。会話画面から再度お試しください。',
                ),
                503 => $redirect->with(
                    'warning',
                    'AIチャットは現在利用できません。時間をおいて再度お試しください。',
                ),
                default => throw $exception,
            };
        }

        if ($request->expectsJson()) {
            return response()->json([
                'user_message' => $result['user_message'],
                'assistant_message' => $result['assistant_message'],
                'conversation' => $result['conversation'],
            ], 201);
        }

        return redirect()
            ->route('ai-chat.conversations.show', $conversation)
            ->with('success', 'メッセージを送信しました。');
    }
}
