<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * AI 相談の demo データシーダー。
 *
 * 固定アカウントに複数の会話とメッセージを投入し、会話履歴・教材コンテキスト・
 * AI 回答エラーの表示と再送を実機確認できる状態を作る。
 *
 * 1. Section に紐づく会話: 2 件
 * 2. Section に紐づかない会話: 1 件
 * 3. AI 回答が error 状態の会話: 1 件
 *
 * 依存順序: `UserSeeder` → `EnrollmentSeeder` → `ContentSeeder` → 本 Seeder。
 */
final class AiChatConversationSeeder extends Seeder
{
    private const STUDENT_EMAIL = 'student@certify-lms.test';

    private const SECTION_TITLE_BINARY = '1.1 2 進数の表現';

    private const SECTION_TITLE_ALGORITHM = '2.1 アルゴリズムの基本';

    private const API_ERROR_MESSAGE =
        'AIからの回答を取得できませんでした。時間をおいて再度お試しください。';

    public function run(): void
    {
        $student = User::query()
            ->where('email', self::STUDENT_EMAIL)
            ->where('role', UserRole::Student->value)
            ->first();

        if ($student === null) {
            $this->command?->warn(
                'AiChatConversationSeeder: 固定受講生が存在しません。先に UserSeeder を実行してください。',
            );

            return;
        }

        $enrollments = $student->enrollments()
            ->whereIn('status', [
                EnrollmentStatus::Learning->value,
                EnrollmentStatus::Passed->value,
            ])
            ->get()
            ->keyBy('certification_id');

        if ($enrollments->isEmpty()) {
            $this->command?->warn(
                'AiChatConversationSeeder: 固定受講生に対象となる受講登録がありません。',
            );

            return;
        }

        $sections = Section::query()
            ->with('chapter.part')
            ->published()
            ->whereIn('title', [
                self::SECTION_TITLE_BINARY,
                self::SECTION_TITLE_ALGORITHM,
            ])
            ->whereHas(
                'chapter.part',
                fn ($query) => $query->whereIn(
                    'certification_id',
                    $enrollments->keys(),
                ),
            )
            ->get()
            ->keyBy('title');

        $binarySection = $sections->get(self::SECTION_TITLE_BINARY);
        $algorithmSection = $sections->get(self::SECTION_TITLE_ALGORITHM);

        if ($binarySection === null || $algorithmSection === null) {
            $this->command?->warn(
                'AiChatConversationSeeder: 固定受講生の受講資格に紐づく対象Sectionが見つかりません。',
            );

            return;
        }

        $binaryCertificationId = $binarySection->chapter?->part?->certification_id;
        $algorithmCertificationId = $algorithmSection->chapter?->part?->certification_id;

        $binaryEnrollment = $enrollments->get($binaryCertificationId);
        $algorithmEnrollment = $enrollments->get($algorithmCertificationId);
        $generalEnrollment = $this->resolveEnrollment($student);

        if (
            ! $binaryEnrollment instanceof Enrollment
            || ! $algorithmEnrollment instanceof Enrollment
            || ! $generalEnrollment instanceof Enrollment
        ) {
            $this->command?->warn(
                'AiChatConversationSeeder: 会話に紐づける受講登録を取得できませんでした。',
            );

            return;
        }

        DB::transaction(function () use (
            $student,
            $binarySection,
            $binaryEnrollment,
            $algorithmSection,
            $algorithmEnrollment,
            $generalEnrollment,
        ): void {
            $this->seedSectionConversation(
                $student,
                $binaryEnrollment,
                $binarySection,
            );

            $this->seedErrorConversation(
                $student,
                $algorithmEnrollment,
                $algorithmSection,
            );

            $this->seedGeneralConversation(
                $student,
                $generalEnrollment,
            );
        });
    }

    /**
     * Section に紐づく通常の相談履歴を作成する。
     */
    private function seedSectionConversation(
        User $student,
        Enrollment $enrollment,
        Section $section,
    ): void {
        $conversation = $this->firstOrCreateConversation(
            $student,
            $enrollment,
            $section,
            '2進数の変換方法について',
        );

        $this->createMessagesIfEmpty($conversation, [
            [
                'role' => AiChatMessageRole::User,
                'content' => '2進数から10進数への変換方法を教えてください。',
                'status' => AiChatMessageStatus::Completed,
                'minutes_ago' => 180,
            ],
            [
                'role' => AiChatMessageRole::Assistant,
                'content' => '2進数を10進数に変換するには、各桁の値に2の累乗を掛けて合計します。',
                'status' => AiChatMessageStatus::Completed,
                'minutes_ago' => 179,
            ],
            [
                'role' => AiChatMessageRole::User,
                'content' => '11001の場合はどうなりますか？',
                'status' => AiChatMessageStatus::Completed,
                'minutes_ago' => 120,
            ],
            [
                'role' => AiChatMessageRole::Assistant,
                'content' => '11001は、1×16 + 1×8 + 0×4 + 0×2 + 1×1 なので、10進数では25です。',
                'status' => AiChatMessageStatus::Completed,
                'minutes_ago' => 119,
            ],
        ]);
    }

    /**
     * AI 回答がエラー状態の相談履歴を作成する。
     */
    private function seedErrorConversation(
        User $student,
        Enrollment $enrollment,
        Section $section,
    ): void {
        $conversation = $this->firstOrCreateConversation(
            $student,
            $enrollment,
            $section,
            'アルゴリズムの計算量について',
        );

        $this->createMessagesIfEmpty($conversation, [
            [
                'role' => AiChatMessageRole::User,
                'content' => 'アルゴリズムの計算量について教えてください。',
                'status' => AiChatMessageStatus::Completed,
                'minutes_ago' => 90,
            ],
            [
                'role' => AiChatMessageRole::Assistant,
                'content' => self::API_ERROR_MESSAGE,
                'status' => AiChatMessageStatus::Error,
                'error_message' => 'Gemini APIから正常な応答を取得できませんでした。',
                'minutes_ago' => 89,
            ],
        ]);
    }

    /**
     * Section に紐づかない通常の相談履歴を作成する。
     */
    private function seedGeneralConversation(
        User $student,
        Enrollment $enrollment,
    ): void {
        $conversation = $this->firstOrCreateConversation(
            $student,
            $enrollment,
            null,
            '資格試験の学習計画について',
        );

        $this->createMessagesIfEmpty($conversation, [
            [
                'role' => AiChatMessageRole::User,
                'content' => '資格試験に向けた学習計画の立て方を教えてください。',
                'status' => AiChatMessageStatus::Completed,
                'minutes_ago' => 60,
            ],
            [
                'role' => AiChatMessageRole::Assistant,
                'content' => 'まず試験日から逆算し、学習範囲を週単位に分けて計画しましょう。',
                'status' => AiChatMessageStatus::Completed,
                'minutes_ago' => 59,
            ],
            [
                'role' => AiChatMessageRole::User,
                'content' => '毎日1時間程度の場合はどう進めればよいですか？',
                'status' => AiChatMessageStatus::Completed,
                'minutes_ago' => 30,
            ],
            [
                'role' => AiChatMessageRole::Assistant,
                'content' => '平日はインプットと短い演習、休日は復習と過去問演習に充てる方法がおすすめです。',
                'status' => AiChatMessageStatus::Completed,
                'minutes_ago' => 29,
            ],
        ]);
    }

    /**
     * 会話を取得し、存在しない場合のみ作成する。
     */
    private function firstOrCreateConversation(
        User $student,
        Enrollment $enrollment,
        ?Section $section,
        string $title,
    ): AiChatConversation {
        $attributes = [
            'user_id' => $student->id,
            'section_id' => $section?->id,
        ];

        if ($section === null) {
            $attributes['title'] = $title;
        }

        $conversation = AiChatConversation::query()->firstOrCreate(
            $attributes,
            [
                'enrollment_id' => $enrollment->id,
                'title' => $title,
                'last_message_at' => null,
                'title_generated_at' => now(),
            ],
        );

        if ($conversation->enrollment_id !== $enrollment->id) {
            $conversation->update([
                'enrollment_id' => $enrollment->id,
            ]);
        }

        return $conversation;
    }

    /**
     * 利用可能なデフォルト受講登録を優先し、なければ最新の受講登録を取得する。
     */
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

    /**
     * メッセージが未登録の場合のみ作成する。
     *
     * @param array<int, array{
     *     role: AiChatMessageRole,
     *     content: string,
     *     status: AiChatMessageStatus,
     *     minutes_ago: int,
     *     error_message?: string
     * }> $messages
     */
    private function createMessagesIfEmpty(
        AiChatConversation $conversation,
        array $messages,
    ): void {
        if ($conversation->messages()->exists()) {
            return;
        }

        $lastMessageAt = null;

        foreach ($messages as $message) {
            $createdAt = now()->subMinutes($message['minutes_ago']);

            $savedMessage = $conversation->messages()->make([
                'role' => $message['role']->value,
                'content' => $message['content'],
                'status' => $message['status']->value,
                'error_message' => $message['error_message'] ?? null,
            ]);

            $savedMessage->timestamps = false;
            $savedMessage->created_at = $createdAt;
            $savedMessage->updated_at = $createdAt;
            $savedMessage->save();

            $lastMessageAt = $createdAt;
        }

        if ($lastMessageAt !== null) {
            $conversation->update([
                'last_message_at' => $lastMessageAt,
            ]);
        }
    }
}
