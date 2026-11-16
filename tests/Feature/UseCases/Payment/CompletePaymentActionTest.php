<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Payment;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Services\MeetingQuotaService;
use App\UseCases\Payment\CompletePaymentAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CompletePaymentActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_completes_payment_and_grants_quota(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create([
            'amount' => 9000,
            'quantity' => 3,
            'stripe_checkout_session_id' => 'cs_complete',
        ]);

        // Act
        $completed = app(CompletePaymentAction::class)(
            paymentId: $payment->id,
            sessionId: 'cs_complete',
            paymentIntentId: 'pi_test',
            amountTotal: 9000,
            currency: 'jpy',
        );

        // Assert
        $this->assertSame(PaymentStatus::Succeeded, $completed->status);
        $this->assertSame('pi_test', $completed->stripe_payment_intent_id);
        $this->assertNotNull($completed->paid_at);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'related_payment_id' => $payment->id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => 3,
        ]);

        $this->assertSame(
            $payment->user->max_meetings + 3,
            app(MeetingQuotaService::class)->remaining($payment->user),
        );
    }

    public function test_duplicate_completion_does_not_grant_quota_twice(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create([
            'amount' => 6000,
            'quantity' => 2,
            'stripe_checkout_session_id' => 'cs_duplicate',
        ]);

        $action = app(CompletePaymentAction::class);

        $arguments = [
            'paymentId' => $payment->id,
            'sessionId' => 'cs_duplicate',
            'paymentIntentId' => 'pi_duplicate',
            'amountTotal' => 6000,
            'currency' => 'jpy',
        ];

        // Act
        $action(...$arguments);
        $action(...$arguments);

        // Assert
        $this->assertSame(
            1,
            MeetingQuotaTransaction::query()
                ->where('related_payment_id', $payment->id)
                ->count(),
        );
    }

    public function test_rejects_mismatched_checkout_session_id(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create([
            'amount' => 6000,
            'stripe_checkout_session_id' => 'cs_expected',
        ]);

        // Act / Assert
        try {
            app(CompletePaymentAction::class)(
                $payment->id,
                'cs_different',
                null,
                6000,
                'jpy',
            );

            $this->fail('Session ID 不一致の場合は例外になるはずです。');
        } catch (RuntimeException) {
            // Assert
            $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
            $this->assertDatabaseMissing('meeting_quota_transactions', [
                'related_payment_id' => $payment->id,
            ]);
        }
    }

    public function test_rejects_mismatched_amount_or_currency(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create([
            'amount' => 6000,
            'stripe_checkout_session_id' => 'cs_amount',
        ]);

        // Act / Assert
        $this->expectException(RuntimeException::class);

        app(CompletePaymentAction::class)(
            $payment->id,
            'cs_amount',
            null,
            5000,
            'jpy',
        );
    }
}
