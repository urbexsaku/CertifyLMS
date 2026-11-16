<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\UseCases\Payment\FailPaymentAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class FailPaymentActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_pending_payment_as_failed(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create([
            'stripe_checkout_session_id' => 'cs_failed',
        ]);

        // Act
        app(FailPaymentAction::class)($payment->id, 'cs_failed');

        // Assert
        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
    }

    public function test_does_not_change_succeeded_payment(): void
    {
        // Arrange
        $payment = Payment::factory()->succeeded()->create([
            'stripe_checkout_session_id' => 'cs_paid',
        ]);

        // Act
        app(FailPaymentAction::class)($payment->id, 'cs_paid');

        // Assert
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
    }

    public function test_rejects_mismatched_checkout_session_id(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create([
            'stripe_checkout_session_id' => 'cs_expected',
        ]);

        // Act / Assert
        $this->expectException(RuntimeException::class);

        app(FailPaymentAction::class)($payment->id, 'cs_different');
    }
}
