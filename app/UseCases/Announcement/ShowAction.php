<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Models\Announcement;

/**
 * admin 用の通知配信履歴の詳細を取得するユースケース。
 * 対象資格・対象ユーザー・配信者を Eager Loading で揃える。
 */
final class ShowAction
{
    public function __invoke(Announcement $announcement): Announcement
    {
        return $announcement
            ->load([
                'targetCertification',
                'targetUser',
                'createdBy',
            ]);
    }
}
