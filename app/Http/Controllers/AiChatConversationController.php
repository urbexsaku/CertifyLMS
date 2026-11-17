<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AiChatConversation\StoreRequest;
use App\Http\Requests\AiChatConversation\UpdateRequest;
use App\Models\AiChatConversation;
use App\Models\User;
use App\UseCases\AiChat\Conversation\DestroyAction;
use App\UseCases\AiChat\Conversation\IndexAction;
use App\UseCases\AiChat\Conversation\ShowAction;
use App\UseCases\AiChat\Conversation\StoreAction;
use App\UseCases\AiChat\Conversation\UpdateAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AI 相談の会話管理を行うコントローラー。
 *
 * 会話一覧・詳細の表示、会話の作成・再利用、タイトル更新、削除を扱う。
 */
class AiChatConversationController extends Controller
{
    public function index(IndexAction $action): View|RedirectResponse
    {
        $conversation = $action($this->user());

        if ($conversation !== null) {
            return redirect()->route(
                'ai-chat.conversations.show',
                $conversation,
            );
        }

        return view('ai-chat.empty-state');
    }

    public function show(AiChatConversation $conversation, ShowAction $action, Request $request): View|JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation = $action($conversation);

        if ($request->expectsJson()) {
            return response()->json([
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'last_message_at' => $conversation->last_message_at,
                    'section_id' => $conversation->section_id,
                ],
                'messages' => $conversation->messages->map(fn ($message) => [
                    'id' => $message->id,
                    'role' => $message->role->value,
                    'content' => $message->content,
                    'status' => $message->status->value,
                    'created_at' => $message->created_at,
                ])->values(),
            ]);
        }

        return view('ai-chat.show', ['conversation' => $conversation]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse|JsonResponse
    {
        $result = $action($this->user(), $request->validated());

        $conversation = $result['conversation'];
        $messageResult = $result['messages'];
        $messageError = $result['message_error'];

        if ($request->expectsJson()) {
            if ($messageError !== null) {
                return $messageError;
            }

            return response()->json([
                'conversation' => $conversation,
                'was_reused' => $result['was_reused'],
                'messages' => $messageResult,
            ], $result['was_reused'] ? 200 : 201);
        }

        $redirect = redirect()->route('ai-chat.conversations.show', $conversation);

        if ($messageError !== null) {
            return match ($messageError->getStatusCode()) {
                502 => $redirect->with(
                    'warning',
                    "AI が応答できませんでした。\nしばらく時間をおいて再試行してください。",
                ),
                429 => $redirect->with(
                    'warning',
                    "本日の利用上限に達しました。\n明日 0:00 以降に再度ご利用ください。",
                ),
                500 => $redirect->with(
                    'warning',
                    '送信に失敗しました。',
                ),
                default => $messageError,
            };
        }

        return $redirect->with(
            'success',
            $result['was_reused']
                ? '会話を再開しました。'
                : '新しい相談を開始しました。',
        );
    }

    public function update(UpdateRequest $request, AiChatConversation $conversation, UpdateAction $action): JsonResponse|RedirectResponse
    {
        $conversation = $action($conversation, $request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'conversation' => $conversation,
            ]);
        }

        return redirect()
            ->route('ai-chat.conversations.show', $conversation)
            ->with('success', 'タイトルを更新しました。');
    }

    public function destroy(AiChatConversation $conversation, DestroyAction $action): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $conversation);

        $action($conversation);

        if (request()->expectsJson()) {
            return response()->json([
                'message' => '会話を削除しました。',
            ]);
        }

        return redirect()
            ->route('ai-chat.index')
            ->with('success', '会話を削除しました。');
    }

    private function user(): User
    {
        $user = request()->user();

        assert($user instanceof User);

        return $user;
    }
}
