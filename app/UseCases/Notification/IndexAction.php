<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 通知一覧を取得するユースケース。
 */
final class IndexAction
{
    public function __invoke(
        User $auth,
        string $tab = 'all',
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = $auth->notifications();

        if ($tab === 'unread') {
            $query->whereNull('read_at');
        }

        return $query
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
