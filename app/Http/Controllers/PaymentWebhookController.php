<?php

namespace App\Http\Controllers;

use App\Contracts\Payments\PaymentGateway;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\PaymentEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, PaymentGateway $gateway): JsonResponse
    {
        if ($provider !== $gateway->name()) {
            throw new BadRequestHttpException("Unknown payment provider '{$provider}'.");
        }

        $payload = $request->getContent();
        $signature = (string) $request->header('X-Payment-Signature', '');

        if ($signature === '' || ! $gateway->verifyWebhookSignature($payload, $signature)) {
            throw new AccessDeniedHttpException('Invalid webhook signature.');
        }

        $event = $gateway->parseWebhookEvent($payload);

        [$paymentEvent, $isNew] = DB::transaction(function () use ($provider, $event, $payload): array {
            $existing = PaymentEvent::query()
                ->where('provider', $provider)
                ->where('provider_event_id', $event->providerEventId)
                ->first();

            if ($existing !== null) {
                return [$existing, false];
            }

            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($payload, associative: true) ?: [];

            $created = PaymentEvent::query()->create([
                'provider' => $provider,
                'provider_event_id' => $event->providerEventId,
                'event_type' => $event->type,
                'payload' => $decoded,
                'received_at' => now(),
            ]);

            return [$created, true];
        });

        if ($isNew) {
            // On the sync queue (used in tests), dispatch() runs inline but
            // can swallow DB::afterCommit callbacks because of job wrapping.
            // dispatchSync keeps the behaviour identical in production (goes
            // through the configured driver) while giving tests deterministic
            // execution.
            if ((string) config('queue.default') === 'sync') {
                ProcessPaymentWebhook::dispatchSync($paymentEvent->id);
            } else {
                ProcessPaymentWebhook::dispatch($paymentEvent->id);
            }
        }

        return response()->json([
            'status' => 'accepted',
            'duplicate' => ! $isNew,
            'event_id' => $paymentEvent->id,
        ]);
    }
}
