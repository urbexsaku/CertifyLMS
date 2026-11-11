<?php

declare(strict_types=1);

namespace App\UseCases\StripeWebhook;

use App\Models\Payment;
use App\Services\StripeWebhookService;
use App\UseCases\Payment\CompletePaymentAction;
use App\UseCases\Payment\FailPaymentAction;
use RuntimeException;
use Stripe\Checkout\Session;

/**
 * Stripe Webhookイベントを処理するAction。
 *
 * イベント種別に応じて決済完了または決済失敗の処理を実行する。
 */
final class HandleAction
{
    public function __construct(
        private readonly StripeWebhookService $stripeWebhookService,
        private readonly CompletePaymentAction $completePaymentAction,
        private readonly FailPaymentAction $failPaymentAction,
    ) {}

    public function __invoke(
        string $payload,
        string $signature,
    ): void {
        $event = $this->stripeWebhookService->constructEvent(
            payload: $payload,
            signature: $signature,
        );

        $supportedEvents = [
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
            'checkout.session.async_payment_failed',
            'checkout.session.expired',
        ];

        if (! in_array($event->type, $supportedEvents, true)) {
            return;
        }

        $session = $event->data->object;

        if (! $session instanceof Session) {
            throw new RuntimeException(
                'Stripe Checkout Sessionを取得できませんでした。',
            );
        }

        $paymentId = $session->metadata->payment_id ?? null;

        // Payment IDが取得できない場合は処理対象外として正常終了する。
        if (! is_string($paymentId) || $paymentId === '') {
            return;
        }

        // 対応するPaymentが存在しない場合もエラーにしない。
        if (! Payment::query()->whereKey($paymentId)->exists()) {
            return;
        }

        $paymentIntentId = is_string($session->payment_intent)
            ? $session->payment_intent
            : ($session->payment_intent->id ?? null);

        switch ($event->type) {
            case 'checkout.session.completed':
                // 非同期決済の場合、completed時点では未払いのことがある。
                if ($session->payment_status !== 'paid') {
                    return;
                }

                $this->completePayment($paymentId, $session, $paymentIntentId);

                return;

            case 'checkout.session.async_payment_succeeded':
                $this->completePayment($paymentId, $session, $paymentIntentId);

                return;

            case 'checkout.session.async_payment_failed':
            case 'checkout.session.expired':
                ($this->failPaymentAction)(
                    paymentId: $paymentId,
                    sessionId: $session->id,
                );

                return;
        }
    }

    private function completePayment(
        string $paymentId,
        Session $session,
        ?string $paymentIntentId,
    ): void {
        ($this->completePaymentAction)(
            paymentId: $paymentId,
            sessionId: $session->id,
            paymentIntentId: $paymentIntentId,
            amountTotal: $session->amount_total,
            currency: $session->currency,
        );
    }
}
