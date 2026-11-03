<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * お知らせ配信機能の開発用シーダー。
 *
 * 全受講生・資格指定・ユーザー指定の3種類のお知らせを作成し、
 * 対象受講生のアプリ内通知も作成する。
 *
 * メール送信は行わない。既存通知テーブルへ直接 INSERT することで、
 * シーディング時にメール通知が送信されることを防ぐ。
 *
 * 依存順序:
 * UserSeeder → CertificationSeeder → EnrollmentSeeder → 本 Seeder
 */
final class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', UserRole::Admin->value)->orderBy('created_at')->first();
        $fixedStudent = User::query()->where('email', 'student@certify-lms.test')->first();
        $certification = Certification::query()->where('name', '基本情報技術者試験')->first();

        if ($admin === null || $fixedStudent === null) {
            $this->command?->warn('AnnouncementSeeder: 管理者あるいは受講生が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        if ($certification === null) {
            $this->command?->warn('AnnouncementSeeder: 基本情報技術者試験が存在しません。先に CertificationSeeder を実行してください。');

            return;
        }

        $allStudents = $this->inProgressStudents()->get();

        $certificationStudents = $this->inProgressStudents()
            ->whereHas('enrollments', function ($query) use ($certification): void {
                $query
                    ->where('certification_id', $certification->id)
                    ->where('status', EnrollmentStatus::Learning->value);
            })
            ->get();

        $individualStudent = new Collection([$fixedStudent]);

        $this->seedAnnouncement(
            admin: $admin,
            title: '【運営】サービス利用に関するお知らせ',
            body: "Certify LMSをご利用いただきありがとうございます。\n"
                ."今後、運営からのお知らせは通知一覧に配信されます。\n"
                .'定期的にご確認ください。',
            targetType: AnnouncementTargetType::AllStudents,
            targetCertification: null,
            targetUser: null,
            recipients: $allStudents,
            dispatchedAt: now()->subDays(3),
        );

        $this->seedAnnouncement(
            admin: $admin,
            title: '【運営】基本情報技術者試験のお知らせ',
            body: "基本情報技術者試験を受講中の皆様へ。\n"
                .'学習計画や進捗を定期的に振り返り、不明点があれば担当コーチへご相談ください。',
            targetType: AnnouncementTargetType::Certification,
            targetCertification: $certification->id,
            targetUser: null,
            recipients: $certificationStudents,
            dispatchedAt: now()->subDays(2),
        );

        $this->seedAnnouncement(
            admin: $admin,
            title: '【運営】個別のお知らせ',
            body: "受講生花子様。\n"
                ."個別のお知らせです。\n"
                .'学習状況を確認しながら、引き続き計画的に学習を進めていきましょう。',
            targetType: AnnouncementTargetType::User,
            targetCertification: null,
            targetUser: $fixedStudent->id,
            recipients: $individualStudent,
            dispatchedAt: now()->subDay(),
        );
    }

    /**
     * 配信対象となる受講中の受講生クエリ。
     */
    private function inProgressStudents(): Builder
    {
        return User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value);
    }

    /**
     * お知らせと対象受講生のアプリ内通知を作成する。
     */
    private function seedAnnouncement(
        User $admin,
        string $title,
        string $body,
        AnnouncementTargetType $targetType,
        ?string $targetCertification,
        ?string $targetUser,
        Collection $recipients,
        Carbon $dispatchedAt,
    ): void {
        if ($recipients->isEmpty()) {
            $this->command?->warn("AnnouncementSeeder: {$title}の配信対象が存在しません。");

            return;
        }

        DB::transaction(function () use (
            $admin,
            $title,
            $body,
            $targetType,
            $targetCertification,
            $targetUser,
            $recipients,
            $dispatchedAt,
        ): void {
            $announcement = Announcement::query()->create([
                'created_by_user_id' => $admin->id,
                'title' => $title,
                'body' => $body,
                'target_type' => $targetType->value,
                'target_certification_id' => $targetCertification,
                'target_user_id' => $targetUser,
                'dispatched_count' => $recipients->count(),
                'dispatched_at' => $dispatchedAt,
            ]);

            foreach ($recipients as $recipient) {
                DB::table('notifications')->insert([
                    'id' => (string) Str::ulid(),
                    'type' => AdminAnnouncementNotification::class,
                    'notifiable_type' => User::class,
                    'notifiable_id' => $recipient->id,
                    'data' => json_encode([
                        'notification_type' => 'admin_announcement',
                        'announcement_id' => $announcement->id,
                        'title' => $announcement->title,
                        'message' => $announcement->title,
                        'body' => $announcement->body,
                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'read_at' => null,
                    'created_at' => $dispatchedAt,
                    'updated_at' => $dispatchedAt,
                ]);
            }
        });
    }
}
