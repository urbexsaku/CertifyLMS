<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\MeetingQuota\Purchase;

use App\Enums\MeetingQuotaTransactionType;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use App\UseCases\MeetingQuota\Purchase\GrantPurchasedQuotaAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class GrantPurchasedQuotaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_grants_purchased_quota_for_succeeded_payment(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $payment = Payment::factory()->for($student)->succeeded()->create([
            'quantity' => 5,
        ]);

        // Act
        $transaction = app(GrantPurchasedQuotaAction::class)($payment);

        // Assert
        $this->assertSame(MeetingQuotaTransactionType::Purchased, $transaction->type);
        $this->assertSame(5, $transaction->amount);
        $this->assertSame($student->id, $transaction->user_id);
        $this->assertSame($payment->id, $transaction->related_payment_id);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => 5,
            'related_payment_id' => $payment->id,
        ]);
    }

    public function test_does_not_grant_quota_twice_for_same_payment(): void
    {
        // Arrange
        $payment = Payment::factory()->succeeded()->create([
            'quantity' => 3,
        ]);

        $action = app(GrantPurchasedQuotaAction::class);

        // Act
        $first = $action($payment);
        $second = $action($payment);

        // Assert
        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            1,
            MeetingQuotaTransaction::query()
                ->where('related_payment_id', $payment->id)
                ->count(),
        );
    }

    public function test_rejects_payment_that_is_not_succeeded(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create();

        // Act / Assert
        $this->expectException(InvalidArgumentException::class);

        app(GrantPurchasedQuotaAction::class)($payment);
    }

    public function test_rejects_non_positive_quantity(): void
    {
        // Arrange
        $payment = Payment::factory()->succeeded()->create([
            'quantity' => 0,
        ]);

        // Act / Assert
        $this->expectException(InvalidArgumentException::class);

        app(GrantPurchasedQuotaAction::class)($payment);
    }
}
