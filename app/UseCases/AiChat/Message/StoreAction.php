<?php

declare(strict_types=1);

namespace App\UseCases\AiChat\Message;

use App\Enums\AiChatMessageStatus;
use App\Exceptions\AiChat\GeminiApiException;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AI 相談のメッセージを保存し、Gemini APIから回答を取得するユースケース。
 *
 * Gemini APIの通信はDBトランザクションの外で実行し、API失敗時も質問とエラー回答を保持する。
 */
final class StoreAction
{
    private const DAILY_LIMIT_ERROR = '本日のAIチャット利用上限に達しました。';

    private const API_ERROR_MESSAGE = 'AIからの回答を取得できませんでした。時間をおいて再度お試しください。';

    private const CONFIGURATION_ERROR_MESSAGE =
        'AIチャットは現在利用できません。時間をおいて再度お試しください。';

    public function __construct(
        private readonly GeminiService $geminiService,
    ) {}

    /**
     * @return array{
     *     user_message: AiChatMessage,
     *     assistant_message: AiChatMessage,
     *     conversation: AiChatConversation
     * }
     */
    public function __invoke(
        AiChatConversation $conversation,
        string $content,
    ): array {
        $user = auth()->user();

        if (! $user instanceof User || $conversation->user_id !== $user->id) {
            throw new AuthorizationException(
                'この会話を利用する権限がありません。',
            );
        }

        if ($this->isDailyLimitExceeded($user->id)) {
            throw new HttpResponseException(
                response()->json([
                    'message' => self::DAILY_LIMIT_ERROR,
                ], 429),
            );
        }

        $shouldGenerateTitle = ! $conversation->messages()
            ->where('role', 'assistant')
            ->where('status', AiChatMessageStatus::Completed->value)
            ->exists();

        [$userMessage, $assistantMessage] = DB::transaction(
            function () use ($conversation, $content): array {
                $userMessage = $conversation->messages()->create([
                    'role' => 'user',
                    'content' => $content,
                    'status' => AiChatMessageStatus::Completed->value,
                ]);

                $assistantMessage = $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => '',
                    'status' => AiChatMessageStatus::Pending->value,
                ]);

                $conversation->update([
                    'last_message_at' => now(),
                ]);

                return [
                    $userMessage,
                    $assistantMessage,
                ];
            },
        );

        $historyLimit = (int) config(
            'ai-chat.conversation.history_limit',
            20,
        );

        $history = $conversation->messages()
            ->where('status', AiChatMessageStatus::Completed->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($historyLimit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (AiChatMessage $message): array => [
                'role' => $message->role->value,
                'content' => $message->content,
            ])
            ->all();

        try {
            $result = $this->geminiService->generateResponse(
                messages: $history,
                section: $conversation->section,
                enrollment: $conversation->enrollment,
            );
        } catch (GeminiApiException $exception) {
            $isConfigurationError = $exception->isConfigurationError();

            $errorMessage = $isConfigurationError
                ? self::CONFIGURATION_ERROR_MESSAGE
                : self::API_ERROR_MESSAGE;

            $statusCode = $isConfigurationError ? 503 : 502;

            $assistantMessage->update([
                'content' => $errorMessage,
                'status' => AiChatMessageStatus::Error,
                'error_message' => $exception->getMessage(),
            ]);

            throw new HttpResponseException(
                response()->json([
                    'message' => $errorMessage,
                    'error_type' => $isConfigurationError
                        ? 'configuration'
                        : 'api',
                    'upstream_status' => $exception->upstreamStatus(),
                ], $statusCode),
            );
        }

        $assistantMessage->update([
            'content' => $result['content'],
            'status' => AiChatMessageStatus::Completed,
            'model_name' => $result['model_name'],
            'input_tokens' => $result['input_tokens'],
            'output_tokens' => $result['output_tokens'],
            'response_time_ms' => $result['response_time_ms'],
        ]);

        if ($shouldGenerateTitle) {
            $this->generateConversationTitle(
                conversation: $conversation,
                question: $userMessage->content,
                answer: $assistantMessage->content,
            );
        }

        return [
            'user_message' => $userMessage->refresh(),
            'assistant_message' => $assistantMessage->refresh(),
            'conversation' => $conversation->refresh(),
        ];
    }

    private function generateConversationTitle(
        AiChatConversation $conversation,
        string $question,
        string $answer,
    ): void {
        if (! config('ai-chat.title.auto_generation_enabled', true)) {
            return;
        }

        if (
            $conversation->title_generated_at !== null
            || $conversation->title_manually_edited_at !== null
        ) {
            return;
        }

        try {
            $title = $this->geminiService->generateTitle(
                question: $question,
                answer: $answer,
            );
        } catch (Throwable $exception) {
            Log::warning(
                'AI chat conversation title generation failed.',
                [
                    'conversation_id' => $conversation->id,
                    'exception' => $exception->getMessage(),
                ],
            );

            return;
        }

        if ($title === '') {
            return;
        }

        AiChatConversation::query()
            ->whereKey($conversation->id)
            ->whereNull('title_generated_at')
            ->whereNull('title_manually_edited_at')
            ->update([
                'title' => $title,
                'title_generated_at' => now(),
            ]);
    }

    private function isDailyLimitExceeded(string $userId): bool
    {
        $today = now('Asia/Tokyo')->toDateString();

        $dailyLimit = (int) config(
            'ai-chat.conversation.daily_limit',
            50,
        );

        $dailyMessageCount = AiChatMessage::query()
            ->whereHas('conversation', function ($query) use ($userId): void {
                $query->where('user_id', $userId);
            })
            ->where('role', 'user')
            ->whereDate('created_at', $today)
            ->count();

        return $dailyMessageCount >= $dailyLimit;
    }
}
