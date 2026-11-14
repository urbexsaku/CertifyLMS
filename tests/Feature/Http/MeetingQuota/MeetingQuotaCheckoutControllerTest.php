<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Enums\PaymentStatus;
use App\Models\Enrollment;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\Checkout\Session;
use Tests\TestCase;

class MeetingQuotaCheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_only_published_packs_in_order(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $first = MeetingPack::factory()->published()->state(['sort_order' => 1])->create();
        $second = MeetingPack::factory()->published()->state(['sort_order' => 2])->create();
        $draft = MeetingPack::factory()->draft()->create();

        // Act
        $response = $this->actingAs($student)->get(route('meeting-quota.checkout.select'));

        // Assert
        $response->assertOk()
            ->assertViewIs('meeting-quota.checkout-select')
            ->assertViewHas('plans', function ($plans) use ($first, $second, $draft) {
                return $plans->modelKeys() === [$first->id, $second->id]
                    && ! $plans->contains('id', $draft->id);
            });
    }

    public function test_store_redirects_to_stripe_checkout_url(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        Enrollment::factory()->for($student, 'user')->learning()->create();
        $pack = MeetingPack::factory()->published()->withCount(3)->withPrice(9000)->create();

        $stripe = \Mockery::mock(StripeCheckoutService::class);
        $stripe->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn(Session::constructFrom([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/test',
            ]));
        $this->app->instance(StripeCheckoutService::class, $stripe);

        // Act
        $response = $this->actingAs($student)->post(
            route('meeting-quota.checkout.create'),
            ['meeting_pack_id' => $pack->id],
        );

        // Assert
        $response->assertRedirect('https://checkout.stripe.com/test');

        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $pack->id,
            'amount' => 9000,
            'quantity' => 3,
            'status' => PaymentStatus::Pending->value,
            'stripe_checkout_session_id' => 'cs_test_123',
        ]);
    }

    public function test_store_rejects_unpublished_pack(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        Enrollment::factory()->for($student, 'user')->learning()->create();
        $pack = MeetingPack::factory()->draft()->create();

        // Act
        $response = $this->actingAs($student)
            ->from(route('meeting-quota.checkout.select'))
            ->post(route('meeting-quota.checkout.create'), [
                'meeting_pack_id' => $pack->id,
            ]);

        // Assert
        $response->assertSessionHasErrors('meeting_pack_id');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_student_without_learning_enrollment_cannot_purchase(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $pack = MeetingPack::factory()->published()->create();

        // Act
        $response = $this->actingAs($student)->post(
            route('meeting-quota.checkout.create'),
            ['meeting_pack_id' => $pack->id],
        );

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_success_displays_own_payment(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $payment = Payment::factory()->for($student)->create([
            'stripe_checkout_session_id' => 'cs_own',
        ]);

        // Act
        $response = $this->actingAs($student)->get(
            route('meeting-quota.checkout.success', ['session_id' => 'cs_own']),
        );

        // Assert
        $response->assertOk()
            ->assertViewIs('meeting-quota.success')
            ->assertViewHas('payment', fn ($actual) => $actual->is($payment));
    }

    public function test_success_does_not_display_another_users_payment(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->create();
        Payment::factory()->for($other)->create([
            'stripe_checkout_session_id' => 'cs_other',
        ]);

        // Act
        $response = $this->actingAs($student)->get(
            route('meeting-quota.checkout.success', ['session_id' => 'cs_other']),
        );

        // Assert
        $response->assertNotFound();
    }

    public function test_success_requires_session_id(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        // Act
        $response = $this->actingAs($student)->get(route('meeting-quota.checkout.success'));

        // Assert
        $response->assertSessionHasErrors('session_id');
    }
}
