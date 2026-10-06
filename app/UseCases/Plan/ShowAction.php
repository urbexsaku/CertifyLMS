<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Models\Plan;

/**
 * プラン詳細を取得するユースケース。
 * 紐づく受講生・作成者・最終更新者を Eager Loading する。。
 */
final class ShowAction
{
    public function __invoke(Plan $plan): Plan
    {
        return $plan
            ->loadCount(['users'])
            ->load(['users', 'createdBy', 'updatedBy']);
    }
}
