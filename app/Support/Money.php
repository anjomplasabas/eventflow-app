<?php

namespace App\Support;

use InvalidArgumentException;
use NumberFormatter;

final readonly class Money
{
    public function __construct(
        public int $minorUnits,
        public string $currency,
    ) {
        if ($currency === '' || strlen($currency) !== 3) {
            throw new InvalidArgumentException("Currency must be a 3-letter ISO 4217 code; got '{$currency}'.");
        }
    }

    public static function zero(string $currency): self
    {
        return new self(0, strtoupper($currency));
    }

    public static function of(int $minorUnits, string $currency): self
    {
        return new self($minorUnits, strtoupper($currency));
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    public function multiplyByQuantity(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Quantity must be non-negative.');
        }

        return new self($this->minorUnits * $quantity, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minorUnits === $other->minorUnits;
    }

    public function format(string $locale = 'en_US'): string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        $fractionDigits = $formatter->getAttribute(NumberFormatter::FRACTION_DIGITS);
        $amount = $this->minorUnits / (10 ** $fractionDigits);
        $formatted = $formatter->formatCurrency($amount, $this->currency);
        if ($formatted === false) {
            throw new \RuntimeException("Unable to format {$this->currency} {$this->minorUnits} for locale {$locale}.");
        }

        return $formatted;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Currency mismatch: {$this->currency} vs {$other->currency}.");
        }
    }
}
