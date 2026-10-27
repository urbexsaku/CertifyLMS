<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;

/**
 * 受講目標(EnrollmentGoal)に対する認可ポリシー。
 *
 * - view: 親 Enrollment の EnrollmentPolicy::view に委譲する
 * - create: 受講生本人の Enrollment に対する目標登録のみ許可
 * - update / delete: 受講生本人の目標のみ更新・削除可
 * - markAchieved / unmarkAchieved: 受講生本人の目標のみ達成状態の変更を許可
 *
 * 目標の所有者判定は親 Enrollment を基準とする。
 */
class EnrollmentGoalPolicy
{
    public function view(User $user, EnrollmentGoal $goal): bool
    {
        return $user->can('view', $goal->enrollment);
    }

    public function create(User $user, Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Student
            && $enrollment->user_id === $user->id;
    }

    public function update(User $user, EnrollmentGoal $goal): bool
    {
        return $user->role === UserRole::Student
            && $goal->enrollment->user_id === $user->id;
    }

    public function delete(User $user, EnrollmentGoal $goal): bool
    {
        return $user->role === UserRole::Student
            && $goal->enrollment->user_id === $user->id;
    }

    public function markAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $user->role === UserRole::Student
            && $goal->enrollment->user_id === $user->id;
    }

    public function unmarkAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $user->role === UserRole::Student
            && $goal->enrollment->user_id === $user->id;
    }
}
