<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingPack\MeetingPackNotDeletableException;
use App\Models\MeetingPack;
use Illuminate\Support\Facades\DB;

/**
 * 面談パックを削除するユースケース。
 *
 * 削除条件: 下書きあるいはアーカイブであること。
 * 違反時は `MeetingPackNotDeletableException`(409)。
 */
final class DestroyAction
{
    /**
     * @throws MeetingPackNotDeletableException
     */
    public function __invoke(MeetingPack $plan): void
    {
        DB::transaction(function () use ($plan) {
            $plan = MeetingPack::query()
                ->lockForUpdate()
                ->findOrFail($plan->id);

            if ($plan->status === MeetingPackStatus::Published) {
                throw new MeetingPackNotDeletableException;
            }

            $plan->delete();
        });
    }
}
