<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanNotDeletableException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * プランを削除するユースケース。
 *
 * 削除条件: 下書きかつ受講生未紐付きであること。
 * 違反時は `PlanNotDeletableException`(409)。
 */
final class DestroyAction
{
    /**
     * @throws PlanNotDeletableException
     */
    public function __invoke(Plan $plan): void
    {
        DB::transaction(function () use ($plan) {
            $plan = Plan::query()
                ->lockForUpdate()
                ->findOrFail($plan->id);

            if ($plan->status !== PlanStatus::Draft || $plan->users()->exists()) {
                throw new PlanNotDeletableException;
            }

            $plan->delete();
        });
    }
}
