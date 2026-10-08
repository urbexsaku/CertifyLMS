<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;

/**
 * 面談パック詳細を取得するユースケース。
 * 作成者・最終更新者を Eager Loading する。
 * Payment モデルおよび MeetingPack::payments() が実装済みの場合は、購入者情報と購入数も取得する。
 */
final class ShowAction
{
    public function __invoke(MeetingPack $meetingPack): MeetingPack
    {
        $with = [
            'createdBy',
            'updatedBy',
        ];

        if (class_exists('App\\Models\\Payment')) {
            $with[] = 'payments.user';
        }

        $meetingPack->load($with);

        if (class_exists('App\\Models\\Payment')) {
            $meetingPack->loadCount('payments');
        }

        return $meetingPack;
    }
}
