<?php

declare(strict_types=1);

namespace App\UseCases\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\UseCases\MeetingQuota\Purchase\GrantPurchasedQuotaAction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stripeの決済完了情報をPaymentに反映するAction。
 *
 * 決済状態を更新し、購入した面談回数を付与する。
 */
final class CompletePaymentAction
{
    public function __construct(
        private readonly GrantPurchasedQuotaAction $grantPurchasedQuotaAction,
    ) {}

    /**
     * Stripeの決済完了情報をPaymentに反映し、購入した面談回数を付与する。
     *
     * @param string $paymentId Payment ID
     * @param string $sessionId Stripe Checkout Session ID
     * @param string|null $paymentIntentId Stripe Payment Intent ID
     * @param int|null $amountTotal 決済金額
     * @param string|null $currency 決済通貨
     *
     * @return Payment 更新されたPayment
     */
    public function __invoke(
        string $paymentId,
        string $sessionId,
        ?string $paymentIntentId,
        ?int $amountTotal,
        ?string $currency,
    ): Payment {
        return DB::transaction(function () use (
            $paymentId,
            $sessionId,
            $paymentIntentId,
            $amountTotal,
            $currency,
        ): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);

            if ($payment->stripe_checkout_session_id !== $sessionId) {
                throw new RuntimeException('Stripe Checkout Session IDが一致しません。');
            }

            if (
                $payment->amount !== $amountTotal
                || $currency !== 'jpy'
            ) {
                throw new RuntimeException(
                    'Stripeの決済金額または通貨が一致しません。',
                );
            }

            if ($payment->status === PaymentStatus::Succeeded) {
                return $payment;
            }

            $payment->update([
                'status' => PaymentStatus::Succeeded,
                'stripe_payment_intent_id' => $paymentIntentId,
                'paid_at' => now(),
            ]);

            ($this->grantPurchasedQuotaAction)($payment->refresh());

            return $payment->refresh();
        });
    }
}
