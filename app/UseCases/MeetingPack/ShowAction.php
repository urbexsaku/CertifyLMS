<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;

/**
 * 面談パック詳細を取得するユースケース。
 *
 * 作成者・最終更新者を Eager Loading する。
 * 購入履歴は購入者情報を含め、直近20件を取得する。
 */
final class ShowAction
{
    public function __invoke(MeetingPack $meetingPack): MeetingPack
    {
        $meetingPack->load([
            'createdBy',
            'updatedBy',
            'payments' => function ($query): void {
                $query
                    ->with('user')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(20);
            },
        ]);

        return $meetingPack;
    }
}
