<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\Services\MeetingQuotaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 受講生本人の面談一覧を取得するユースケース。
 *
 * filter に応じて upcoming / past / all を切り替える。
 *
 * @return LengthAwarePaginator<Meeting>
 */
final class IndexAction
{
    public function __construct(
        private readonly MeetingQuotaService $meetingQuota,
    ) {}

    public function __invoke(
        User $auth,
        ?string $filter = null,
        int $perPage = 20,
    ): array {
        $filter ??= 'upcoming';

        $query = Meeting::query()
            ->with(['enrollment.certification', 'coach'])
            ->forStudent($auth)
            ->orderByDesc('scheduled_at');

        $meetings = match ($filter) {
            'past' => $query->past()->paginate($perPage),
            'all' => $query->paginate($perPage),
            default => $query->upcoming()->paginate($perPage),
        };

        return [
            'meetings' => $meetings,
            'meetingRemaining' => $this->meetingQuota->remaining($auth),
        ];
    }
}
