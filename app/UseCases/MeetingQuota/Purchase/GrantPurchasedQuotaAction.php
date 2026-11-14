<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota\Purchase;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 購入した面談回数を付与するAction。
 */
final class GrantPurchasedQuotaAction
{
    public function __invoke(Payment $payment): MeetingQuotaTransaction
    {
        if ($payment->quantity <= 0) {
            throw new InvalidArgumentException('付与する面談回数は正の整数で指定してください。');
        }

        return DB::transaction(function () use ($payment): MeetingQuotaTransaction {
            $payment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::Succeeded) {
                throw new InvalidArgumentException(
                    '決済完了済みの購入のみ面談回数を付与できます。',
                );
            }

            return MeetingQuotaTransaction::firstOrCreate(
                ['related_payment_id' => $payment->id],
                [
                    'user_id' => $payment->user_id,
                    'type' => MeetingQuotaTransactionType::Purchased,
                    'amount' => $payment->quantity,
                    'occurred_at' => now(),
                ],
            );
        });
    }
}
