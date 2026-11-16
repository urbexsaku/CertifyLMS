<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota\Checkout;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripeCheckoutService;
use Throwable;

/**
 * 追加面談パックの購入用Checkout Sessionを作成するAction。
 *
 * Paymentを作成し、Stripe Checkout SessionのURLを返す。
 */
final class CreateCheckoutSessionAction
{
    public function __construct(
        private readonly StripeCheckoutService $stripeCheckoutService,
    ) {}

    public function __invoke(User $user, string $meetingPackId): string
    {
        $meetingPack = MeetingPack::query()->published()->findOrFail($meetingPackId);

        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'meeting_pack_id' => $meetingPack->id,
            'amount' => $meetingPack->price,
            'quantity' => $meetingPack->meeting_count,
            'status' => PaymentStatus::Pending,
        ]);

        try {
            $session = $this->stripeCheckoutService->createCheckoutSession(
                user: $user,
                meetingPack: $meetingPack,
                payment: $payment,
            );

            $payment->update([
                'stripe_checkout_session_id' => $session->id,
            ]);

            return $session->url;
        } catch (Throwable $exception) {
            $payment->update([
                'status' => PaymentStatus::Failed,
            ]);

            throw $exception;
        }
    }
}
