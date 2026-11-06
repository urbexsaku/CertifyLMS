<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Enrollment;
use App\Services\MeetingAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * 予約画面で表示する面談の空き枠を取得するユースケース。
 *
 * 指定された受講情報の資格に基づき、指定日の空き枠を取得する。
 */
final class FetchAvailabilityAction
{
    public function __construct(
        private readonly MeetingAvailabilityService $availabilityService,
    ) {}

    /**
     * @return Collection<int, array{slot_start: Carbon, slot_end: Carbon, available_coach_count: int}>
     */
    public function __invoke(Enrollment $enrollment, Carbon $date): Collection
    {
        $certification = $enrollment->loadMissing('certification')->certification;

        return $this->availabilityService->slotsForCertification(
            $certification,
            $date,
        );
    }
}
