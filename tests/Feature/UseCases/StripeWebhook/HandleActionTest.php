<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\StripeWebhook;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\UseCases\StripeWebhook\HandleAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\Webhook;
use Tests\TestCase;

class HandleActionTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'whsec_handle_test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.webhook_secret' => $this->secret]);
    }

    public function test_completed_paid_event_completes_payment_and_grants_quota(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create([
            'amount' => 9000,
            'quantity' => 3,
            'stripe_checkout_session_id' => 'cs_handle_paid',
        ]);

        // Act
        $this->handleEvent([
            'id' => 'evt_paid',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'object' => 'checkout.session',
                    'id' => 'cs_handle_paid',
                    'metadata' => ['payment_id' => $payment->id],
                    'payment_status' => 'paid',
                    'payment_intent' => 'pi_handle_paid',
                    'amount_total' => 9000,
                    'currency' => 'jpy',
                ],
            ],
        ]);

        // Assert
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'related_payment_id' => $payment->id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => 3,
        ]);
    }

    public function test_async_payment_succeeded_event_completes_payment_and_grants_quota(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create([
            'amount' => 9000,
            'quantity' => 3,
            'stripe_checkout_session_id' => 'cs_async_succeeded',
        ]);

        // Act
        $this->handleEvent([
            'id' => 'evt_async_succeeded',
            'type' => 'checkout.session.async_payment_succeeded',
            'data' => [
                'object' => [
                    'object' => 'checkout.session',
                    'id' => 'cs_async_succeeded',
                    'metadata' => ['payment_id' => $payment->id],
                    'payment_status' => 'paid',
                    'payment_intent' => 'pi_async_succeeded',
                    'amount_total' => 9000,
                    'currency' => 'jpy',
                ],
            ],
        ]);

        // Assert
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'related_payment_id' => $payment->id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => 3,
        ]);
    }

    public function test_completed_unpaid_event_does_not_complete_payment(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create([
            'amount' => 9000,
            'quantity' => 3,
            'stripe_checkout_session_id' => 'cs_unpaid',
        ]);

        // Act
        $this->handleEvent([
            'id' => 'evt_unpaid',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'object' => 'checkout.session',
                    'id' => 'cs_unpaid',
                    'metadata' => ['payment_id' => $payment->id],
                    'payment_status' => 'unpaid',
                    'amount_total' => 9000,
                    'currency' => 'jpy',
                ],
            ],
        ]);

        // Assert
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertDatabaseMissing('meeting_quota_transactions', [
            'related_payment_id' => $payment->id,
        ]);
    }

    public function test_async_payment_failed_event_fails_payment_without_granting_quota(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create([
            'stripe_checkout_session_id' => 'cs_async_failed',
        ]);

        // Act
        $this->handleEvent([
            'id' => 'evt_async_failed',
            'type' => 'checkout.session.async_payment_failed',
            'data' => [
                'object' => [
                    'object' => 'checkout.session',
                    'id' => 'cs_async_failed',
                    'metadata' => ['payment_id' => $payment->id],
                ],
            ],
        ]);

        // Assert
        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);

        $this->assertDatabaseMissing('meeting_quota_transactions', [
            'related_payment_id' => $payment->id,
        ]);
    }

    public function test_expired_event_marks_pending_payment_as_failed(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create([
            'stripe_checkout_session_id' => 'cs_expired',
        ]);

        // Act
        $this->handleEvent([
            'id' => 'evt_expired',
            'type' => 'checkout.session.expired',
            'data' => [
                'object' => [
                    'object' => 'checkout.session',
                    'id' => 'cs_expired',
                    'metadata' => ['payment_id' => $payment->id],
                ],
            ],
        ]);

        // Assert
        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertDatabaseMissing('meeting_quota_transactions', [
            'related_payment_id' => $payment->id,
        ]);
    }

    public function test_ignores_unsupported_event_type(): void
    {
        // Arrange
        $event = [
            'id' => 'evt_ignored',
            'type' => 'customer.created',
            'data' => [
                'object' => [
                    'object' => 'customer',
                    'id' => 'cus_test',
                ],
            ],
        ];

        // Act
        $this->handleEvent($event);

        // Assert
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_missing_payment_id_is_ignored(): void
    {
        // Arrange
        $event = [
            'id' => 'evt_missing_payment_id',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'object' => 'checkout.session',
                    'id' => 'cs_missing_payment_id',
                    'metadata' => [],
                    'payment_status' => 'paid',
                    'amount_total' => 9000,
                    'currency' => 'jpy',
                ],
            ],
        ];

        // Act
        $this->handleEvent($event);

        // Assert
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_event_for_nonexistent_payment_is_ignored(): void
    {
        // Arrange
        $event = [
            'id' => 'evt_nonexistent_payment',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'object' => 'checkout.session',
                    'id' => 'cs_nonexistent_payment',
                    'metadata' => [
                        'payment_id' => '01J00000000000000000000000',
                    ],
                    'payment_status' => 'paid',
                    'amount_total' => 9000,
                    'currency' => 'jpy',
                ],
            ],
        ];

        // Act
        $this->handleEvent($event);

        // Assert
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
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

    /**
     * @param array<string, mixed> $event
     */
    private function handleEvent(array $event): void
    {
        // Arrange
        $payload = json_encode([
            'id' => $event['id'],
            'object' => 'event',
            'api_version' => '2024-06-20',
            'created' => now()->timestamp,
            'type' => $event['type'],
            'data' => $event['data'],
        ], JSON_THROW_ON_ERROR);

        $signature = $this->generateSignature($payload, $this->secret);

        // Act
        app(HandleAction::class)(
            payload: $payload,
            signature: $signature,
        );
    }
}
