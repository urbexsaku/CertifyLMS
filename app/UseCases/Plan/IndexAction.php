<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 管理者向けのプランマスタ一覧をフィルタ付きで取得するユースケース。
 *
 * フィルタ: keyword(プラン名部分一致) / status(下書き・公開中・アーカイブ)
 * 公開中を優先し、sort_order 昇順、同値の場合は作成日時降順で並び替え、20件/ページで取得する。
 */
final class IndexAction
{
    public function __invoke(
        ?string $keyword,
        ?PlanStatus $status = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Plan::query()
            ->withCount('users');

        if ($keyword !== null && $keyword !== '') {
            $query->where('name', 'LIKE', '%'.$keyword.'%');
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $query
            ->orderByRaw(
                'CASE WHEN status = ? THEN 0 ELSE 1 END',
                [PlanStatus::Published->value]
            )
            ->ordered()
            ->paginate($perPage)
            ->withQueryString();
    }
}
