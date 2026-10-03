<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;

/**
 * 面談パック詳細を取得するユースケース。
 * 直近20件の購入履歴と作成者・最終更新者を Eager Loadingし、購入数を併記する。
 */
final class ShowAction
{
    // public function __invoke(MeetingPack $plan): MeetingPack
    // {
    //     return $plan
    //         ->load([
    //             'createdBy',
    //             'updatedBy',
    //             'payments' => fn ($q) => $q
    //                 ->latest('paid_at')
    //                 ->limit(20),
    //             'payment.user',
    //         ])
    //         ->loadCount('payments');
    // }
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
