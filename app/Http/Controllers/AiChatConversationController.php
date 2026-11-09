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
use App\UseCases\AiChat\Message\StoreAction as StoreMessageAction;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    public function show(AiChatConversation $conversation, ShowAction $action): View
    {
        $this->authorize('view', $conversation);

        return view('ai-chat.show', [
            'conversation' => $action($conversation),
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action, StoreMessageAction $messageAction): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $result = $action($this->user(), $validated);

        $conversation = $result['conversation'];
        $message = trim($validated['message'] ?? '');

        $messageResult = null;
        $messageError = null;

        if ($message !== '') {
            try {
                $messageResult = $messageAction(
                    $conversation,
                    $message,
                );
            } catch (HttpResponseException $exception) {
                $messageError = $exception->getResponse();
            }
        }

        if ($request->expectsJson()) {
            if ($messageError !== null) {
                return $messageError;
            }

            return response()->json([
                'conversation' => $conversation->refresh(),
                'was_reused' => $result['was_reused'],
                'messages' => $messageResult,
            ], $result['was_reused'] ? 200 : 201);
        }

        $redirect = redirect()->route('ai-chat.conversations.show', $conversation);

        if ($messageError !== null) {
            return match ($messageError->getStatusCode()) {
                502 => $redirect->with(
                    'warning',
                    '質問は保存しましたが、AIから回答を取得できませんでした。会話画面から再度お試しください。',
                ),
                429 => $redirect->with(
                    'warning',
                    '会話を作成しましたが、本日の利用上限に達しているため質問を送信できませんでした。',
                ),
                503 => $redirect->with(
                    'warning',
                    'AIチャットは現在利用できません。時間をおいて再度お試しください。',
                ),
                default => $messageError,
            };
        }

        return $redirect->with(
            'success',
            $message !== ''
                ? '相談を開始しました。'
                : ($result['was_reused']
                    ? '会話を再開しました。'
                    : '新しい相談を開始しました。'),
        );
    }

    public function update(UpdateRequest $request, AiChatConversation $conversation, UpdateAction $action): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $conversation);

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

    public function destroy(AiChatConversation $conversation, DestroyAction $action, IndexAction $indexAction): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $conversation);

        $action($conversation);

        if (request()->expectsJson()) {
            return response()->json([
                'message' => '会話を削除しました。',
            ]);
        }

        $nextConversation = $indexAction($this->user());

        $redirect = $nextConversation !== null
            ? redirect()->route('ai-chat.conversations.show', $nextConversation)
            : redirect()->route('ai-chat.index');

        return $redirect->with('success', '会話を削除しました。');
    }

    private function user(): User
    {
        $user = request()->user();

        assert($user instanceof User);

        return $user;
    }
}
