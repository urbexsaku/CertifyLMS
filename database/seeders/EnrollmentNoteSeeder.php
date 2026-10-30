<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Seeder;

final class EnrollmentNoteSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();
        $studentNoQuota = User::query()->where('email', 'student-noquota@certify-lms.test')->first();
        $coachTaro = User::query()->where('email', 'coach@certify-lms.test')->first();
        $coachHanako = User::query()->where('email', 'coach2@certify-lms.test')->first();

        if ($student === null || $studentNoQuota === null || $coachTaro === null || $coachHanako === null) {
            $this->command?->warn('EnrollmentNoteSeeder: 必要な固定アカウントが存在しません。先に UserSeeder を実行してください。');

            return;
        }

        // 受講生花子の受講登録にメモを投入する。
        $this->seedNote($student, '基本情報技術者試験', $coachTaro, '基本情報技術者試験の学習状況を確認しました。');
        $this->seedNote($student, 'TOEIC L&R 800 点コース', $coachTaro, 'TOEICの学習進捗を確認しました。');
        $this->seedNote($student, 'TOEIC L&R 800 点コース', $coachHanako, 'リスニング対策についてアドバイスしました。');
        $this->seedNote($student, '日商簿記 2 級', $coachHanako, '簿記の学習状況を確認しました。');

        // 面談残数なしの受講生の受講登録にもメモを投入する。
        $this->seedNote($studentNoQuota, '基本情報技術者試験', $coachTaro, '学習状況を確認しました。');
    }

    private function seedNote(User $student, string $certificationName, User $coach, string $body): void
    {
        $enrollment = Enrollment::query()
            ->where('user_id', $student->id)
            ->whereHas('certification', fn ($query) => $query->where('name', $certificationName))
            ->first();

        if ($enrollment === null) {
            $this->command?->warn("EnrollmentNoteSeeder: {$student->email} の {$certificationName} の受講登録が存在しません。");

            return;
        }

        $enrollment->notes()->firstOrCreate([
            'author_id' => $coach->id,
            'body' => $body,
        ]);
    }
}
