<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiChatMessage;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * AI チャットの日次利用上限を管理する Service。
 */
final class AiChatDailyLimitService
{
    /**
     * 当日のユーザーメッセージ数が上限に達していないことを確認する。
     *
     * @throws HttpResponseException
     */
    public function assertCanSend(User $user): void
    {
        $limit = (int) config('ai-chat.conversation.daily_limit', 50);

        $startOfDay = now()->startOfDay();
        $startOfNextDay = $startOfDay->copy()->addDay();

        $count = AiChatMessage::query()
            ->where('role', 'user')
            ->where('created_at', '>=', $startOfDay)
            ->where('created_at', '<', $startOfNextDay)
            ->whereHas(
                'conversation',
                fn ($query) => $query->where('user_id', $user->id),
            )
            ->count();

        if ($count < $limit) {
            return;
        }

        throw new HttpResponseException(
            response()->json([
                'message' => "本日の利用上限に達しました。\n明日 0:00 以降に再度ご利用ください。",
            ], 429),
        );
    }
}
