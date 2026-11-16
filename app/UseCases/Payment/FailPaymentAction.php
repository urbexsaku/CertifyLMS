<?php

declare(strict_types=1);

namespace App\UseCases\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stripeの決済失敗・有効期限切れをPaymentに反映するAction。
 */
final class FailPaymentAction
{
    public function __invoke(
        string $paymentId,
        string $sessionId,
    ): void {
        DB::transaction(function () use (
            $paymentId,
            $sessionId,
        ): void {
            $payment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($paymentId);

            if ($payment->stripe_checkout_session_id !== $sessionId) {
                throw new RuntimeException(
                    'Stripe Checkout Session IDが一致しません。',
                );
            }

            if ($payment->status !== PaymentStatus::Pending) {
                return;
            }

            $payment->update([
                'status' => PaymentStatus::Failed,
            ]);
        });
    }
}
