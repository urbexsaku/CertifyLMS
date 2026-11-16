<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\UseCases\StripeWebhook\HandleAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

final class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly HandleAction $handleAction,
    ) {}

    public function handle(Request $request): Response
    {
        try {
            ($this->handleAction)(
                payload: $request->getContent(),
                signature: (string) $request->header('Stripe-Signature', ''),
            );
        } catch (SignatureVerificationException|UnexpectedValueException $exception) {
            report($exception);

            return response('Invalid Stripe webhook signature or payload.', 400);
        }

        return response('OK', 200);
    }
}
