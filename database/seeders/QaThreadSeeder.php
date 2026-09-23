<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * 質問掲示板（QaThread）の開発用シーダー。
 *
 * **設計思想（状態網羅 + 固定アカウント）**:
 *
 * 1. **公開資格のスレッド状態網羅**: 公開中の5資格それぞれに、
 *    未解決・回答なし / 未解決・回答1件 / 未解決・回答2件 /
 *    解決済み・回答1件 / 解決済み・回答2件の5パターンを作成する。
 *    回答数・解決状態による絞り込み、回答表示、ページネーション等を確認可能にする。
 *
 * 2. **固定 student の質問データ**: `student@certify-lms.test` を
 *    公開資格25スレッドすべての投稿者として使用する。
 *    「自分の質問」一覧、スレッドの編集・削除、解決 / 未解決変更、
 *    解決マークの動線等を固定アカウントで確認できるようにする。
 *
 * 3. **公開停止中資格の表示制御確認**: 準備中資格と販売終了資格に
 *    回答なし・未解決のスレッドを各1件作成する。
 *    生徒・コーチには表示されず、管理者には表示される状態を確認可能にする。
 *
 * 4. **検索・並び順確認用データ**: 複数資格のスレッドに
 *    「模擬試験」「試験直前」等の共通キーワードを設定し、
 *    キーワード検索と資格フィルターを組み合わせた動作を確認できるようにする。
 *    また、公開資格25スレッドの作成日時を5時間ずつずらし、
 *    新着順および20件 / ページのページネーションを確認可能にする。
 *
 * 依存順序: `UserSeeder` → `CertificationSeeder` → 本 Seeder。
 */
final class QaThreadSeeder extends Seeder
{
    public function run(): void
    {
        $publishedCertifications = Certification::query()
            ->where('status', CertificationStatus::Published->value)
            ->with('questionCategories')
            ->orderBy('created_at')
            ->get();

        if ($publishedCertifications->isEmpty()) {
            $this->command?->warn('QaThreadSeeder: 公開済資格がありません。先に CertificationSeeder を実行してください。');

            return;
        }

        $fixedStudent = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        if ($fixedStudent === null) {
            $this->command?->warn('QaThreadSeeder: 固定studentが存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $this->seedPublishedThreads($publishedCertifications, $fixedStudent);
        $this->seedNonPublishedThreads($fixedStudent);
    }

    /**
     * 公開資格5件に共通する5パターンのスレッドを作成する。
     *
     * @param Collection<int, Certification> $certifications
     */
    private function seedPublishedThreads(Collection $certifications, User $student): void
    {
        $patterns = [
            [
                'title' => '学習順序について質問です',
                'body' => '学習を始める場合、どの分野から進めるのがおすすめでしょうか？',
                'status' => QaThreadStatus::Open->value,
                'resolved_hours_after' => null,
            ],
            [
                'title' => '学習方法について質問です',
                'body' => '学習でつまずいています。おすすめの勉強方法があれば教えてください。',
                'status' => QaThreadStatus::Open->value,
                'resolved_hours_after' => null,
            ],
            [
                'title' => '模擬試験の復習方法について',
                'body' => '模擬試験で間違えた問題は、どのように復習すると効果的でしょうか？',
                'status' => QaThreadStatus::Open->value,
                'resolved_hours_after' => null,
            ],
            [
                'title' => '苦手分野の学習順序について',
                'body' => '苦手な分野を優先して学習したいのですが、どのような順番がおすすめでしょうか？',
                'status' => QaThreadStatus::Resolved->value,
                'resolved_hours_after' => 3,
            ],
            [
                'title' => '試験直前の学習計画について',
                'body' => '試験直前はどのような学習計画にするとよいでしょうか？',
                'status' => QaThreadStatus::Resolved->value,
                'resolved_hours_after' => 5,
            ],
        ];

        foreach ($certifications as $certificationIndex => $certification) {
            foreach ($patterns as $index => $pattern) {
                $hoursAgo = ($certificationIndex * 5 + $index + 1) * 5;

                $this->createThread(
                    $certification,
                    $student,
                    $pattern,
                    $hoursAgo,
                );
            }
        }
    }

    /**
     * 準備中 / 販売終了資格のスレッドを作成する。
     */
    private function seedNonPublishedThreads(User $student): void
    {
        $draftCertification = Certification::query()
            ->where('name', 'AWS Certified Solutions Architect (準備中)')
            ->where('status', CertificationStatus::Draft->value)
            ->first();

        if ($draftCertification !== null) {
            $this->createThread(
                $draftCertification,
                $student,
                [
                    'title' => 'AWS資格について質問です',
                    'body' => '現在準備中のAWS Certified Solutions Architectについての質問です。',
                    'status' => QaThreadStatus::Open->value,
                    'resolved_hours_after' => null,
                ],
                100,
            );
        }

        $archivedCertification = Certification::query()
            ->where('name', '販売終了: Webクリエイター能力認定試験')
            ->where('status', CertificationStatus::Archived->value)
            ->first();

        if ($archivedCertification !== null) {
            $this->createThread(
                $archivedCertification,
                $student,
                [
                    'title' => '過去の学習方法について',
                    'body' => '販売終了したWebクリエイター能力認定試験について、過去の学習方法を質問します。',
                    'status' => QaThreadStatus::Open->value,
                    'resolved_hours_after' => null,
                ],
                120,
            );
        }
    }

    /**
     * スレッドを作成し、作成日時・解決日時を明示的に設定する。
     */
    private function createThread(
        Certification $certification,
        User $student,
        array $config,
        int $hoursAgo,
    ): QaThread {
        $createdAt = now()->subHours($hoursAgo);

        $resolvedAt = $config['resolved_hours_after'] !== null
            ? $createdAt->copy()->addHours($config['resolved_hours_after'])
            : null;

        $thread = QaThread::factory()
            ->for($certification)
            ->for($student)
            ->create([
                'status' => $config['status'],
                'title' => $config['title'],
                'body' => $config['body'],
            ]);

        $thread->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'resolved_at' => $resolvedAt,
        ])->save();

        return $thread;
    }
}
