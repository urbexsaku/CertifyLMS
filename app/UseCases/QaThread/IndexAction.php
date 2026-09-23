<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 質問掲示板スレッド一覧をフィルタ付きで取得するユースケース。
 *
 * - admin: 全資格配下の QaThread
 * - coach: 公開中の担当資格(certification.coaches)配下の QaThread のみ
 * - student: 公開中の全資格配下の QaThread
 * フィルタ: keyword(スレッドタイトル、本文、回答本文の部分一致) / certification_id / status(未解決・解決済)
 */
final class IndexAction
{
    public function __invoke(
        User $auth,
        ?string $keyword = null,
        ?string $certificationId = null,
        ?string $status = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = QaThread::query()
            ->with(['user', 'certification'])
            ->withCount('replies');

        if ($auth->role === UserRole::Student) {
            $query->whereHas(
                'certification',
                fn ($q) => $q->where('status', CertificationStatus::Published->value),
            );
        }

        if ($auth->role === UserRole::Coach) {
            $query->whereHas(
                'certification',
                fn ($q) => $q->where('status', CertificationStatus::Published->value)
                    ->whereHas(
                        'coaches',
                        fn ($coachQuery) => $coachQuery->where('users.id', $auth->id),
                    ),
            );
        }

        if ($certificationId !== null && $certificationId !== '') {
            $query->forCertification($certificationId);
        }

        $query->byStatus($status);
        $query->keyword($keyword);

        return $query
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
