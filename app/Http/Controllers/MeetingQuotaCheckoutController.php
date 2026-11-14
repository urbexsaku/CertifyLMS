<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MeetingPackStatus;
use App\Http\Requests\MeetingQuota\Checkout\StoreRequest;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\UseCases\MeetingQuota\Checkout\CreateCheckoutSessionAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeetingQuotaCheckoutController extends Controller
{
    public function index(): View
    {
        $plans = MeetingPack::query()
            ->where('status', MeetingPackStatus::Published)
            ->ordered()
            ->get();

        return view('meeting-quota.checkout-select', ['plans' => $plans]);
    }

    public function store(StoreRequest $request, CreateCheckoutSessionAction $action): RedirectResponse
    {
        $checkoutUrl = $action($request->user(), $request->validated('meeting_pack_id'));

        return redirect()->away($checkoutUrl);
    }

    public function success(Request $request): View
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string'],
        ]);

        $payment = Payment::query()
            ->where('user_id', $request->user()->getKey())
            ->where('stripe_checkout_session_id', $validated['session_id'])
            ->firstOrFail();

        return view('meeting-quota.success', [
            'payment' => $payment,
        ]);
    }
}
