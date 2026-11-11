<?php

declare(strict_types=1);

namespace Tests\Feature\Http\StripeWebhook;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\Webhook;
use Tests\TestCase;

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

    /**
     * Stripe Webhook のテスト用署名を生成する。
     */
    private function generateSignature(string $payload, string $secret): string
    {
        $timestamp = time();

        $signature = hash_hmac(
            'sha256',
            "{$timestamp}.{$payload}",
            $secret,
        );

        return "t={$timestamp},v1={$signature}";
    }
}
