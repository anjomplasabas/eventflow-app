<?php

namespace App\Http\Controllers;

use App\Contracts\Payments\PaymentGateway;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\Payment;
use App\Models\PaymentEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Local sandbox UI that stands in for a real provider's hosted checkout.
 * Clicking "Pay" or "Fail" synthesizes a webhook event and feeds it through
 * the production path so the integration is exercised end-to-end.
 */
class PaymentSandboxController extends Controller
{
    public function show(Request $request, string $reference): Response|RedirectResponse
    {
        $payment = Payment::query()
            ->where('reference', $reference)
            ->with('registration.event.organizer', 'registration.ticketType')
            ->firstOrFail();

        if ($payment->registration->user_id !== $request->user()?->id) {
            abort(403);
        }

        return Inertia::render('payments/sandbox', [
            'payment' => [
                'reference' => $payment->reference,
                'amount_display' => $payment->amountMoney()->format(),
                'currency' => $payment->currency,
                'status' => $payment->status->value,
            ],
            'event' => [
                'title' => $payment->registration->event->title,
                'slug' => $payment->registration->event->slug,
            ],
            'organizer' => [
                'slug' => $payment->registration->event->organizer->slug,
                'name' => $payment->registration->event->organizer->name,
            ],
        ]);
    }

    public function complete(Request $request, string $reference, PaymentGateway $gateway): RedirectResponse
    {
        $outcome = $request->validate([
            'outcome' => ['required', 'in:succeed,fail'],
        ])['outcome'];

        $payment = Payment::query()->where('reference', $reference)->firstOrFail();
        if ($payment->registration->user_id !== $request->user()?->id) {
            abort(403);
        }

        $event = DB::transaction(function () use ($gateway, $payment, $outcome): PaymentEvent {
            $payload = [
                'id' => 'evt_'.(string) Str::uuid(),
                'type' => $outcome === 'succeed' ? 'payment.succeeded' : 'payment.failed',
                'payment_reference' => $payment->reference,
                'data' => [
                    'provider_reference' => 'pi_'.Str::lower(Str::random(16)),
                ],
            ];

            return PaymentEvent::query()->create([
                'provider' => $gateway->name(),
                'provider_event_id' => (string) $payload['id'],
                'event_type' => (string) $payload['type'],
                'payload' => $payload,
                'received_at' => now(),
            ]);
        });

        ProcessPaymentWebhook::dispatchSync($event->id);

        $registration = $payment->fresh()?->registration;
        if ($registration === null) {
            return redirect()->route('events.index');
        }

        return redirect()->route('events.registration.confirmation', [
            'organizer' => $registration->event->organizer->slug,
            'event' => $registration->event->slug,
            'reference' => $registration->reference,
        ]);
    }
}
