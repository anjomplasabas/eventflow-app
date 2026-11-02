<?php

use App\Support\Money;

it('normalizes currency to uppercase via ::of', function () {
    $money = Money::of(1050, 'usd');

    expect($money->currency)->toBe('USD')
        ->and($money->minorUnits)->toBe(1050);
});

it('rejects currency codes that are not 3 letters', function () {
    Money::of(100, 'US');
})->throws(InvalidArgumentException::class);

it('adds and subtracts amounts within the same currency', function () {
    $a = Money::of(2500, 'USD');
    $b = Money::of(750, 'USD');

    expect($a->add($b)->minorUnits)->toBe(3250)
        ->and($a->subtract($b)->minorUnits)->toBe(1750);
});

it('refuses to add amounts in different currencies', function () {
    Money::of(100, 'USD')->add(Money::of(100, 'EUR'));
})->throws(InvalidArgumentException::class, 'Currency mismatch');

it('multiplies by a non-negative integer quantity', function () {
    $price = Money::of(1500, 'USD');

    expect($price->multiplyByQuantity(0)->isZero())->toBeTrue()
        ->and($price->multiplyByQuantity(3)->minorUnits)->toBe(4500);
});

it('rejects negative multiplier', function () {
    Money::of(100, 'USD')->multiplyByQuantity(-1);
})->throws(InvalidArgumentException::class);

it('reports equality by both currency and amount', function () {
    expect(Money::of(100, 'USD')->equals(Money::of(100, 'USD')))->toBeTrue()
        ->and(Money::of(100, 'USD')->equals(Money::of(100, 'EUR')))->toBeFalse()
        ->and(Money::of(100, 'USD')->equals(Money::of(200, 'USD')))->toBeFalse();
});

it('formats to a currency string for a given locale', function () {
    $formatted = Money::of(123456, 'USD')->format('en_US');

    expect($formatted)->toContain('1,234.56');
});
