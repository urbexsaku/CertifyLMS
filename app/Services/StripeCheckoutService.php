<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

/**
 * Stripe Checkout Session の作成を担当する Service。
 */
class StripeCheckoutService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * 面談パック購入用のStripe Checkout Sessionを作成する。
     *
     * @param User $user 購入者
     * @param MeetingPack $meetingPack 購入対象の面談パック
     * @param Payment $payment 決済情報
     *
     * @return Session 作成されたCheckout Session
     */
    public function createCheckoutSession(
        User $user,
        MeetingPack $meetingPack,
        Payment $payment,
    ): Session {
        return $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => 'jpy',
                    'unit_amount' => $payment->amount,
                    'product_data' => [
                        'name' => $meetingPack->name,
                        'description' => "面談{$payment->quantity}回分",
                    ],
                ],
                'quantity' => 1,
            ]],
            'client_reference_id' => (string) $payment->getKey(),
            'metadata' => [
                'payment_id' => (string) $payment->getKey(),
            ],
            'success_url' => route('meeting-quota.checkout.success')
                .'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('dashboard.index'),
        ]);
    }
}
