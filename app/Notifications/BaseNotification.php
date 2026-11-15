<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * 通知クラスの基底クラス。
 *
 * 通知処理をキューに登録して非同期で実行し、
 * メール通知には専用のキューを割り当てる。
 *
 * 一時的な送信失敗に備えたリトライ設定も共通化する。
 */
abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * 最大試行回数
     */
    public int $tries = 3;

    /**
     * リトライ間隔（秒）
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * メール通知のキュー名を指定する。
     *
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return [
            'mail' => 'notifications',
        ];
    }
}
