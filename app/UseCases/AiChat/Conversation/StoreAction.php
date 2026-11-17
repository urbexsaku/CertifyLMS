<?php

declare(strict_types=1);

namespace App\UseCases\AiChat\Conversation;

use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Section;
use App\Models\User;
use App\UseCases\AiChat\Message\StoreAction as StoreMessageAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * AI 相談の会話を作成、または既存の会話を再利用するユースケース。
 *
 * Section が指定された場合は受講生本人に有効な受講登録（学習中または合格済み）があることを確認する。
 * 同じ受講生・同じ Section の会話が存在する場合は最新の会話を再利用する。
 * 初回メッセージが指定された場合は、メッセージ送信Actionを呼び出す。
 */
final class StoreAction
{
    public function __construct(
        private readonly StoreMessageAction $messageStore,
    ) {}

    /**
     * @param array{
     *     section_id?: ?string,
     *     message?: ?string,
     *     source?: ?string
     * } $validated
     *
     * @return array{
     *     conversation: AiChatConversation,
     *     was_reused: bool,
     *     messages: ?array{
     *         user_message: AiChatMessage,
     *         assistant_message: AiChatMessage,
     *         conversation: AiChatConversation
     *     },
     *     message_error: ?JsonResponse
     * }
     */
    public function __invoke(
        User $student,
        array $validated,
    ): array {
        $result = DB::transaction(function () use (
            $student,
            $validated,
        ): array {
            $sectionId = $validated['section_id'] ?? null;
            $section = null;
            $enrollmentId = null;

            if ($sectionId !== null) {
                $section = Section::query()
                    ->with('chapter.part.certification')
                    ->whereKey($sectionId)
                    ->first();

                if ($section === null) {
                    throw new AuthorizationException(
                        'この教材に対する AI 相談は利用できません。',
                    );
                }

                $certification = $section->chapter?->part?->certification;

                if ($certification === null) {
                    throw new AuthorizationException(
                        'この教材に対する AI 相談は利用できません。',
                    );
                }

                $enrollment = $certification->enrollments()
                    ->where('user_id', $student->id)
                    ->whereIn('status', [
                        EnrollmentStatus::Learning->value,
                        EnrollmentStatus::Passed->value,
                    ])
                    ->first();

                if ($enrollment === null) {
                    throw new AuthorizationException(
                        'この教材に対する AI 相談は利用できません。',
                    );
                }

                $enrollmentId = $enrollment->id;

                if (($validated['source'] ?? null) === 'widget') {
                    $conversation = AiChatConversation::query()
                        ->where('user_id', $student->id)
                        ->where('section_id', $section->id)
                        ->orderByDesc('last_message_at')
                        ->orderByDesc('id')
                        ->first();

                    if ($conversation !== null) {
                        return [
                            'conversation' => $conversation,
                            'was_reused' => true,
                        ];
                    }
                }
            }

            $initialMessage = trim($validated['message'] ?? '');

            $title = $initialMessage !== ''
                ? mb_substr($initialMessage, 0, 30)
                : (string) config('ai-chat.title.provisional', '新規相談');

            $conversation = AiChatConversation::create([
                'user_id' => $student->id,
                'enrollment_id' => $enrollmentId,
                'section_id' => $section?->id,
                'title' => $title,
            ]);

            return [
                'conversation' => $conversation,
                'was_reused' => false,
            ];
        });

        $conversation = $result['conversation'];
        $initialMessage = trim($validated['message'] ?? '');

        $messages = null;
        $messageError = null;

        if ($initialMessage !== '') {
            try {
                $messages = ($this->messageStore)(
                    $conversation,
                    $initialMessage,
                );
            } catch (HttpResponseException $exception) {
                $response = $exception->getResponse();

                if (! ($response instanceof JsonResponse)) {
                    throw $exception;
                }

                $messageError = $response;
            }
        }

        return [
            'conversation' => $conversation->refresh(),
            'was_reused' => $result['was_reused'],
            'messages' => $messages,
            'message_error' => $messageError,
        ];
    }
}
