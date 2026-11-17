<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * AI チャットメッセージの処理状態。
 *
 * - Pending: 処理待ち
 * - Completed: 処理完了
 * - Error: 処理失敗
 */
enum AiChatMessageStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Error = 'error';
}
