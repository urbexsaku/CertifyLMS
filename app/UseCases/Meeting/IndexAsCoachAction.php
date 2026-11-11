<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * コーチ宛の面談一覧を取得するユースケース。
 *
 * 受講生・受講情報で絞り込み、filter に応じて upcoming / past / all を切り替える。
 *
 * @return LengthAwarePaginator<Meeting>
 */
final class IndexAsCoachAction
{
    public function __invoke(
        User $auth,
        ?string $filter = null,
        ?string $studentId = null,
        ?string $enrollmentId = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $filter ??= 'upcoming';

        $query = Meeting::query()
            ->with(['enrollment.certification', 'student'])
            ->forCoach($auth)
            ->when($studentId, fn ($q, $id) => $q->where('student_id', $id))
            ->when($enrollmentId, fn ($q, $id) => $q->where('enrollment_id', $id));

        return match ($filter) {
            'past' => $query->past()->orderByDesc('scheduled_at')->paginate($perPage),
            'all' => $query->orderByDesc('scheduled_at')->paginate($perPage),
            default => $query->upcoming()->orderBy('scheduled_at')->paginate($perPage),
        };
    }
}
