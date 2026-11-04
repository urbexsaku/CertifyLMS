<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

final class EnrollmentGoalSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFixedStudentGoal();
        $this->seedDemoStudentGoal();
    }

    private function seedFixedStudentGoal(): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();
        if ($student === null) {
            return;
        }

        $enrollment = Enrollment::query()
            ->where('user_id', $student->id)
            ->orderBy('created_at')
            ->first();
        if ($enrollment === null) {
            return;
        }

        $patterns = [
            [
                'title' => '学習計画を立てる',
                'description' => '試験日から逆算して学習スケジュールを作成し、毎週の学習時間と進める範囲を決める。',
                'target_date' => now()->addDays(7)->toDateString(),
                'achieved_at' => null,
                'minutes_ago' => 40,
            ],
            [
                'title' => '苦手分野を克服する',
                'description' => '模擬試験や問題演習の結果から苦手分野を特定し、重点的に復習して正答率を上げる。',
                'target_date' => now()->addDays(14)->toDateString(),
                'achieved_at' => null,
                'minutes_ago' => 30,
            ],
            [
                'title' => '模擬試験で80点以上を取る',
                'description' => '模擬試験を定期的に受験し、間違えた問題の原因を分析して、80点以上を安定して取れるようにする。',
                'target_date' => now()->addDays(14)->toDateString(),
                'achieved_at' => null,
                'minutes_ago' => 20,
            ],
            [
                'title' => '毎日学習を継続する',
                'description' => '毎日少しずつ学習を進め、学習習慣を身につける。',
                'target_date' => null,
                'achieved_at' => null,
                'minutes_ago' => 10,
            ],
            [
                'title' => '資格試験に合格する',
                'description' => '学習計画に沿って各分野の学習と過去問演習を完了し、資格試験に合格する。',
                'target_date' => now()->addDays(7)->toDateString(),
                'achieved_at' => now()->subDays(2),
                'minutes_ago' => 5,
            ],
        ];

        foreach ($patterns as $goal) {
            $createdAt = Carbon::now()->subMinutes($goal['minutes_ago']);
            $goal = EnrollmentGoal::create([
                'enrollment_id' => $enrollment->id,
                'title' => $goal['title'],
                'description' => $goal['description'],
                'target_date' => $goal['target_date'],
                'achieved_at' => $goal['achieved_at'],
            ]);
            $goal->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }
    }

    private function seedDemoStudentGoal(): void
    {
        $demoStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNotIn('email', ['student@certify-lms.test', 'student-noquota@certify-lms.test'])
            ->whereHas('enrollments')
            ->limit(2)
            ->get();

        foreach ($demoStudents as $student) {
            $enrollment = Enrollment::query()
                ->where('user_id', $student->id)
                ->orderBy('created_at')
                ->first();

            if ($enrollment === null) {
                continue;
            }

            EnrollmentGoal::create([
                'enrollment_id' => $enrollment->id,
                'title' => '学習計画を立てる',
                'description' => '試験日から逆算して学習スケジュールを作成する。',
                'target_date' => now()->addDays(14)->toDateString(),
                'achieved_at' => null,
            ]);
        }
    }
}
