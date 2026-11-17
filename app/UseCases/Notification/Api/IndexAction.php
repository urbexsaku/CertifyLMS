<?php

declare(strict_types=1);

namespace App\UseCases\Notification\Api;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 通知 API の一覧取得 Action。
 *
 * tab = 'unread' の場合は未読通知のみ、
 * それ以外は全通知を時系列降順で返す。
 */
final class IndexAction
{
    /**
     * @return LengthAwarePaginator<DatabaseNotification>
     */
    public function __invoke(
        User $user,
        string $tab = 'all',
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = $tab === 'unread'
            ? $user->unreadNotifications()
            : $user->notifications();

        return $query->orderByDesc('created_at')->paginate($perPage);
    }
}
