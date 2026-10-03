<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 管理者向けの面談パックマスタ一覧をフィルタ付きで取得するユースケース。
 *
 * フィルタ: keyword(パック名部分一致) / status(下書き・公開中・アーカイブ)
 * sort_order 昇順、同値の場合は作成日時降順で並び替え、20件/ページで取得する。
 */
final class IndexAction
{
    public function __invoke(
        ?string $keyword,
        ?MeetingPackStatus $status = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = MeetingPack::query();

        if ($keyword !== null && $keyword !== '') {
            $query->where('name', 'LIKE', '%'.$keyword.'%');
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $query
            ->ordered()
            ->paginate($perPage)
            ->withQueryString();
    }
}
