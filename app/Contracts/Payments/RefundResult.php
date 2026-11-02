<?php

namespace App\Contracts\Payments;

final readonly class RefundResult
{
    public function __construct(
        public string $providerReference,
        public string $status,
    ) {}

    public function succeeded(): bool
    {
        return $this->status === 'succeeded';
    }
}
