<?php

namespace App\Contracts\Payments;

final readonly class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $providerEventId,
        public string $type,
        public string $paymentReference,
        public array $data,
    ) {}
}
