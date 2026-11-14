<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_is_cast_to_payment_status_enum(): void
    {
        // Arrange
        $payment = Payment::factory()->succeeded()->create();

        // Act
        $status = $payment->fresh()->status;

        // Assert
        $this->assertSame(PaymentStatus::Succeeded, $status);
    }

    public function test_amount_and_quantity_are_cast_to_integer(): void
    {
        // Arrange
        $payment = Payment::factory()->create([
            'amount' => '9000',
            'quantity' => '3',
        ]);

        // Act
        $fresh = $payment->fresh();

        // Assert
        $this->assertIsInt($fresh->amount);
        $this->assertIsInt($fresh->quantity);
    }

    public function test_paid_at_is_cast_to_datetime(): void
    {
        // Arrange
        $payment = Payment::factory()->succeeded()->create();

        // Act
        $paidAt = $payment->fresh()->paid_at;

        // Assert
        $this->assertInstanceOf(Carbon::class, $paidAt);
    }

    public function test_user_relation_returns_owner(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $payment = Payment::factory()->for($student)->create();

        // Act
        $user = $payment->user;

        // Assert
        $this->assertTrue($user->is($student));
    }

    public function test_meeting_pack_relation_returns_purchased_pack(): void
    {
        // Arrange
        $pack = MeetingPack::factory()->published()->create();
        $payment = Payment::factory()->for($pack)->create();

        // Act
        $meetingPack = $payment->meetingPack;

        // Assert
        $this->assertTrue($meetingPack->is($pack));
    }
}
