<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * 質問掲示板（QaReply）の開発用シーダー。
 *
 * **設計思想（回答パターン + 回答者の固定ルール）**:
 *
 * 1. **公開資格の回答パターン**: 公開中の5資格それぞれに、
 *    回答なし / コーチによる回答1件 / 生徒・コーチによる回答2件を
 *    組み合わせ、計30件の回答を作成する。
 *    回答数、回答者種別、回答順等を確認可能にする。
 *
 * 2. **回答者の割り当て**: 回答1件のパターンでは対象資格の担当コーチを、
 *    回答2件のパターンでは受講中のデモ受講生と担当コーチを使用する。
 *    回答2件の生徒回答には、固定studentを使用しない。
 *
 * 3. **回答本文の検索確認**: 「模擬試験」「試験直前」等のキーワードを
 *    回答本文にも設定し、スレッド本文と同様に回答本文が検索対象となる
 *    ことを確認できるようにする。
 *
 * 4. **回答日時の設定**: 回答はスレッド作成日時より1時間後、
 *    回答2件のパターンでは1件目を1時間後、2件目を2時間後に設定し、
 *    回答一覧が投稿順（古い順）で表示されることを確認可能にする。
 *
 * 5. **公開停止中資格**: 準備中資格・販売終了資格のスレッドには
 *    回答を作成しない。管理者のみ閲覧可能な未回答スレッドとして確認する。
 *
 * 依存順序: `UserSeeder` → `CertificationSeeder` → `QaThreadSeeder` → 本 Seeder。
 */
final class QaReplySeeder extends Seeder
{
    public function run(): void
    {
        $inProgressStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNot('email', 'student@certify-lms.test')
            ->orderBy('created_at')
            ->get();

        $inProgressStudentIndex = 0;

        $certifications = Certification::query()
            ->where('status', CertificationStatus::Published->value)
            ->with('coaches')
            ->orderBy('created_at')
            ->get();

        foreach ($certifications as $certification) {
            $coach = $certification->coaches->first();

            if ($coach === null) {
                continue;
            }

            $threads = QaThread::query()
                ->where('certification_id', $certification->id)
                ->orderBy('created_at')
                ->get();

            $this->createReplies(
                $threads,
                $coach,
                $inProgressStudents,
                $inProgressStudentIndex,
            );
        }
    }

    /**
     * スレッドのパターンに応じて回答を作成する。
     *
     * @param Collection<int, QaThread> $threads
     * @param Collection<int, User> $inProgressStudents
     */
    private function createReplies(
        Collection $threads,
        User $coach,
        Collection $inProgressStudents,
        int &$inProgressStudentIndex,
    ): void {
        foreach ($threads as $thread) {
            match ($thread->title) {
                '学習方法について質問です' => $this->createReply(
                    $thread,
                    $coach,
                    'まずは章ごとに演習を解き、つまずいたら本文に戻る進め方がおすすめです。',
                    1,
                ),
                '模擬試験の復習方法について' => $this->createTwoReplies(
                    $thread,
                    $inProgressStudents,
                    $coach,
                    $inProgressStudentIndex,
                    '私は模擬試験の復習で「間違えた理由」をノートに分けて書いていました。模擬試験の復習に役立ちました。',
                    'コーチとしては、模擬試験で間違えた理由を知識不足・読み違い・時間不足に分けて整理する方法をおすすめします。',
                ),
                '苦手分野の学習順序について' => $this->createReply(
                    $thread,
                    $coach,
                    '苦手分野は、出題頻度が高いものから優先して進めると効率的です。',
                    1,
                ),
                '試験直前の学習計画について' => $this->createTwoReplies(
                    $thread,
                    $inProgressStudents,
                    $coach,
                    $inProgressStudentIndex,
                    '試験直前は新しい問題よりも、これまで間違えた問題の復習を優先していました。',
                    '試験直前は、これまでの誤答と頻出論点を短時間で見直せる形にまとめておくと効果的です。',
                ),
                default => null,
            };
        }
    }

    /**
     * 1件の回答を作成する。
     */
    private function createReply(
        QaThread $thread,
        User $user,
        string $body,
        int $hoursAfterThread,
    ): void {
        $createdAt = $thread->created_at->copy()->addHours($hoursAfterThread);

        QaReply::factory()
            ->forThread($thread)
            ->forUser($user)
            ->create()
            ->forceFill([
                'body' => $body,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
    }

    /**
     * 受講生・コーチによる2件の回答を作成する。
     */
    private function createTwoReplies(
        QaThread $thread,
        Collection $inProgressStudents,
        User $coach,
        int &$inProgressStudentIndex,
        string $studentBody,
        string $coachBody,
    ): void {
        if ($inProgressStudents->isEmpty()) {
            return;
        }

        $student = $inProgressStudents[$inProgressStudentIndex % $inProgressStudents->count()];
        $inProgressStudentIndex++;

        $this->createReply(
            $thread,
            $student,
            $studentBody,
            1,
        );

        $this->createReply(
            $thread,
            $coach,
            $coachBody,
            2,
        );
    }
}
