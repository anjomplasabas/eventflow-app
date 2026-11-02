<?php

namespace App\Contracts\Payments;

final readonly class CheckoutSession
{
    public function __construct(
        public string $providerReference,
        public string $redirectUrl,
    ) {}
}
