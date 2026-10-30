<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

/**
 * 受講メモ(EnrollmentNote)に対する認可ポリシー。
 *
 * - viewAny: admin / 担当coachのみ。親 Enrollment の EnrollmentPolicy::view に委譲する
 * - create: admin / 当該資格の担当coachのみ
 * - update / delete: メモ作成者である担当coach、または admin のみ
 * - edit画面の表示: update の認可を使用する
 *
 * student / 担当外coachはメモの参照・登録・更新・削除不可。
 * 親 Enrollment が取得できない場合、または論理削除済みの場合はすべての操作を拒否する。
 *
 * coach の担当判定は certification.coaches リレーション(certification_coach_assignments 経由)で行う。
 * メモ作成者の判定は EnrollmentNote.author_id を基準とする。
 */
class EnrollmentNotePolicy
{
    public function viewAny(User $user, Enrollment $enrollment): bool
    {
        if ($enrollment->trashed()) {
            return false;
        }

        return in_array($user->role, [UserRole::Admin, UserRole::Coach], true)
            && $user->can('view', $enrollment);
    }

    public function create(User $user, Enrollment $enrollment): bool
    {
        if ($enrollment->trashed()) {
            return false;
        }

        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->isAssignedCoach($enrollment, $user),
            default => false,
        };
    }

    public function update(User $user, EnrollmentNote $note): bool
    {
        $note->loadMissing('enrollment');
        $enrollment = $note->enrollment;

        if ($enrollment === null || $enrollment->trashed()) {
            return false;
        }

        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->isAssignedCoach($enrollment, $user)
                && $note->author_id === $user->id,
            default => false,
        };
    }

    public function delete(User $user, EnrollmentNote $note): bool
    {
        $note->loadMissing('enrollment');
        $enrollment = $note->enrollment;

        if ($enrollment === null || $enrollment->trashed()) {
            return false;
        }

        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->isAssignedCoach($enrollment, $user)
                && $note->author_id === $user->id,
            default => false,
        };
    }

    private function isAssignedCoach(Enrollment $enrollment, User $coach): bool
    {
        $enrollment->loadMissing('certification.coaches');

        return $enrollment->certification?->coaches->contains('id', $coach->id) ?? false;
    }
}
