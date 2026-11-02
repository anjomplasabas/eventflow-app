<?php

use App\Enums\TicketKind;
use App\Models\TicketType;
use App\Support\Money;

it('returns a Money value matching the stored minor units and currency', function () {
    $t = new TicketType([
        'kind' => TicketKind::Paid,
        'price_minor' => 4500,
        'currency' => 'EUR',
    ]);

    $money = $t->priceMoney();

    expect($money)->toBeInstanceOf(Money::class)
        ->and($money->minorUnits)->toBe(4500)
        ->and($money->currency)->toBe('EUR');
});

it('reports free tickets correctly', function () {
    $free = new TicketType(['kind' => TicketKind::Free, 'price_minor' => 0, 'currency' => 'USD']);
    $paid = new TicketType(['kind' => TicketKind::Paid, 'price_minor' => 1000, 'currency' => 'USD']);

    expect($free->isFree())->toBeTrue()
        ->and($paid->isFree())->toBeFalse();
});
