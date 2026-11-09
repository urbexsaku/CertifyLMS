<?php

declare(strict_types=1);

namespace App\UseCases\AiChat\Conversation;

use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * AI 相談の会話を作成、または既存の会話を再利用するユースケース。
 *
 * Section が指定された場合は受講生本人の学習対象であることを確認する。
 * 同じ受講生・同じ Section の会話が存在する場合は最新の会話を再利用する。
 */
final class StoreAction
{
    /**
     * @param array{section_id?: ?string} $validated
     *
     * @return array{
     *     conversation: AiChatConversation,
     *     was_reused: bool
     * }
     */
    public function __invoke(
        User $student,
        array $validated,
    ): array {
        return DB::transaction(function () use (
            $student,
            $validated,
        ): array {
            $sectionId = $validated['section_id'] ?? null;
            $section = null;

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
            } else {
                $enrollment = $this->resolveEnrollment($student);

                if ($enrollment === null) {
                    throw new AuthorizationException(
                        'AI 相談を利用できる受講資格がありません。',
                    );
                }
            }

            $conversation = AiChatConversation::create([
                'user_id' => $student->id,
                'enrollment_id' => $enrollment->id,
                'section_id' => $section?->id,
                'title' => (string) config(
                    'ai-chat.title.provisional',
                    '新規相談',
                ),
            ]);

            return [
                'conversation' => $conversation,
                'was_reused' => false,
            ];
        });
    }

    private function resolveEnrollment(User $student): ?Enrollment
    {
        $statuses = [
            EnrollmentStatus::Learning->value,
            EnrollmentStatus::Passed->value,
        ];

        $defaultEnrollment = $student->defaultEnrollment()
            ->whereIn('status', $statuses)
            ->first();

        if ($defaultEnrollment !== null) {
            return $defaultEnrollment;
        }

        return $student->enrollments()
            ->whereIn('status', $statuses)
            ->orderByDesc('created_at')
            ->first();
    }
}
