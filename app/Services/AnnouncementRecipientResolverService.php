<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * 配信対象の条件に応じて、お知らせ通知の配信先を取得するサービス。
 */
final class AnnouncementRecipientResolverService
{
    /**
     * 指定された配信対象に該当する受講生を取得する。
     *
     * @param array{target_type: string, target_certification_id?: ?string, target_user_id?: ?string} $validated
     *
     * @return Collection<int, User>
     */
    public function resolve(array $validated): Collection
    {
        $query = User::query()
            ->where('role', UserRole::Student)
            ->where('status', UserStatus::InProgress);

        return match ($validated['target_type']) {
            AnnouncementTargetType::AllStudents->value => $query->get(),

            AnnouncementTargetType::Certification->value => $query->whereHas('enrollments', function ($query) use ($validated) {
                $query->learning()
                    ->where('certification_id', $validated['target_certification_id']);
            })->get(),

            AnnouncementTargetType::User->value => $query->whereKey($validated['target_user_id'])->get(),
        };
    }
}
