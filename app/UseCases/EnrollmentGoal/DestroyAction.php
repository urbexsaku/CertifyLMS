<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;
use Illuminate\Support\Facades\DB;

/**
 * 受講生による目標を削除するユースケース。
 */
final class DestroyAction
{
    public function __invoke(EnrollmentGoal $goal): void
    {
        DB::transaction(fn () => $goal->delete());
    }
}
