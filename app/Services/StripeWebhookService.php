<?php

declare(strict_types=1);

namespace App\Services;

use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Stripe Webhook の署名検証とイベント取得を担当する Service。
 */
final class StripeWebhookService
{
    /**
     * Stripe Webhookの署名を検証し、イベントを取得する。
     *
     * @throws UnexpectedValueException
     * @throws SignatureVerificationException
     */
    public function constructEvent(
        string $payload,
        string $signature,
    ): Event {
        return Webhook::constructEvent(
            $payload,
            $signature,
            config('services.stripe.webhook_secret'),
        );
    }
}
