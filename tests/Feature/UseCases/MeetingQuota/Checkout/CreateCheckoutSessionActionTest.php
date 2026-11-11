<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\MeetingQuota\Checkout;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripeCheckoutService;
use App\UseCases\MeetingQuota\Checkout\CreateCheckoutSessionAction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Stripe\Checkout\Session;
use Tests\TestCase;

class CreateCheckoutSessionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_pending_payment_and_returns_checkout_url(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        $pack = MeetingPack::factory()
            ->published()
            ->withCount(3)
            ->withPrice(9000)
            ->create();

        $session = Session::constructFrom([
            'id' => 'cs_test_checkout_session',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_checkout_session',
        ]);

        $stripe = Mockery::mock(StripeCheckoutService::class);
        $stripe->shouldReceive('createCheckoutSession')
            ->once()
            ->withArgs(function ($user, $meetingPack, $payment) use ($student, $pack) {
                return $user->is($student)
                    && $meetingPack->is($pack)
                    && $payment instanceof Payment;
            })
            ->andReturn($session);

        $this->app->instance(StripeCheckoutService::class, $stripe);

        // Act
        $url = app(CreateCheckoutSessionAction::class)(
            $student,
            $pack->id,
        );

        // Assert
        $this->assertSame($session->url, $url);

        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $pack->id,
            'amount' => 9000,
            'quantity' => 3,
            'status' => PaymentStatus::Pending->value,
            'stripe_checkout_session_id' => $session->id,
        ]);

        $this->assertDatabaseMissing('meeting_quota_transactions', [
            'related_payment_id' => Payment::query()
                ->where('stripe_checkout_session_id', $session->id)
                ->value('id'),
        ]);
    }

    public function test_does_not_create_payment_for_unpublished_pack(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $pack = MeetingPack::factory()->draft()->create();

        $stripe = Mockery::mock(StripeCheckoutService::class);
        $stripe->shouldNotReceive('createCheckoutSession');

        $this->app->instance(StripeCheckoutService::class, $stripe);

        // Act / Assert
        try {
            app(CreateCheckoutSessionAction::class)(
                $student,
                $pack->id,
            );

            $this->fail('非公開パックは購入できないはずです。');
        } catch (ModelNotFoundException) {
            // Assert
            $this->assertDatabaseCount('payments', 0);
        }
    }

    public function test_marks_payment_failed_when_stripe_session_creation_fails(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $pack = MeetingPack::factory()->published()->create();

        $stripe = Mockery::mock(StripeCheckoutService::class);
        $stripe->shouldReceive('createCheckoutSession')
            ->once()
            ->andThrow(new RuntimeException('Stripe API error'));

        $this->app->instance(StripeCheckoutService::class, $stripe);

        // Act / Assert
        try {
            app(CreateCheckoutSessionAction::class)(
                $student,
                $pack->id,
            );

            $this->fail('Stripeのセッション作成失敗時は例外が発生するはずです。');
        } catch (RuntimeException $exception) {
            // Assert
            $this->assertSame('Stripe API error', $exception->getMessage());

            $payment = Payment::query()->firstOrFail();

            $this->assertSame(
                PaymentStatus::Failed,
                $payment->status,
            );
        }
    }
}
