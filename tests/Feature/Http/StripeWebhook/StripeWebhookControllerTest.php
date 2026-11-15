<?php

declare(strict_types=1);

namespace Tests\Feature\Http\StripeWebhook;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Stripe\Webhook;
use Tests\TestCase;

#[Group('external-api')]
class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.webhook_secret' => 'whsec_test_secret',
        ]);
    }

    public function test_handle_returns_ok_for_valid_signature(): void
    {
        // Arrange
        $payload = json_encode([
            'id' => 'evt_test_unknown',
            'object' => 'event',
            'type' => 'customer.created',
            'data' => [
                'object' => [
                    'id' => 'cus_test',
                    'object' => 'customer',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = $this->generateSignature(
            $payload,
            'whsec_test_secret',
        );

        // Act
        $response = $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $signature,
            ],
            $payload,
        );

        // Assert
        $response->assertOk();
    }

    public function test_handle_returns_bad_request_for_invalid_signature(): void
    {
        // Arrange
        $payload = json_encode([
            'id' => 'evt_test_unknown',
            'object' => 'event',
            'type' => 'customer.created',
            'data' => [
                'object' => [
                    'id' => 'cus_test',
                    'object' => 'customer',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        // Act
        $response = $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 'invalid_signature',
            ],
            $payload,
        );

        // Assert
        $response->assertBadRequest();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_handle_returns_bad_request_when_signature_is_missing(): void
    {
        // Arrange
        $payload = json_encode([
            'id' => 'evt_test_unknown',
            'object' => 'event',
            'type' => 'customer.created',
            'data' => [
                'object' => [
                    'id' => 'cus_test',
                    'object' => 'customer',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        // Act
        $response = $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload,
        );

        // Assert
        $response->assertBadRequest();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_handle_returns_bad_request_for_invalid_payload(): void
    {
        // Arrange
        $payload = '{invalid json';

        $signature = $this->generateSignature(
            $payload,
            'whsec_test_secret',
        );

        // Act
        $response = $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $signature,
            ],
            $payload,
        );

        // Assert
        $response->assertBadRequest();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_handle_returns_bad_request_for_expired_signature(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create();

        $payload = json_encode([
            'id' => 'evt_expired_signature',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'object' => 'checkout.session',
                    'id' => 'cs_expired_signature',
                    'metadata' => [
                        'payment_id' => $payment->id,
                    ],
                    'payment_status' => 'paid',
                    'amount_total' => $payment->amount,
                    'currency' => 'jpy',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = $this->generateSignature(
            $payload,
            'whsec_test_secret',
            time() - 600,
        );

        // Act
        $response = $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $signature,
            ],
            $payload,
        );

        // Assert
        $response->assertBadRequest();

        $this->assertSame(
            PaymentStatus::Pending,
            $payment->fresh()->status
        );

        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_duplicate_webhook_request_does_not_grant_quota_twice(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create([
            'amount' => 9000,
            'quantity' => 3,
            'stripe_checkout_session_id' => 'cs_duplicate_http',
        ]);

        $payload = json_encode([
            'id' => 'evt_duplicate_http',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'object' => 'checkout.session',
                    'id' => 'cs_duplicate_http',
                    'metadata' => [
                        'payment_id' => $payment->id,
                    ],
                    'payment_status' => 'paid',
                    'payment_intent' => 'pi_duplicate_http',
                    'amount_total' => 9000,
                    'currency' => 'jpy',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = $this->generateSignature(
            $payload,
            'whsec_test_secret',
        );

        // Act
        $firstResponse = $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $signature,
            ],
            $payload,
        );

        $secondResponse = $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $signature,
            ],
            $payload,
        );

        // Assert
        $firstResponse->assertOk();
        $secondResponse->assertOk();

        $this->assertSame(
            PaymentStatus::Succeeded,
            $payment->fresh()->status
        );

        $this->assertDatabaseCount('meeting_quota_transactions', 1);
    }

    /**
     * Stripe Webhook のテスト用署名を生成する。
     */
    private function generateSignature(
        string $payload,
        string $secret,
        ?int $timestamp = null,
    ): string {
        $timestamp ??= time();

        $signature = hash_hmac(
            'sha256',
            "{$timestamp}.{$payload}",
            $secret,
        );

        return "t={$timestamp},v1={$signature}";
    }
}
