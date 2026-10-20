<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

/**
 * 目標の状態を達成から未達成へ変更するユースケース。
 */
final class UnmarkAchievedAction
{
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        if ($goal->achieved_at === null) {
            return $goal->refresh();
        }

        $goal->update([
            'achieved_at' => null,
        ]);

        return $goal->refresh();
    }
}
