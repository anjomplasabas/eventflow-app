<?php

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\TicketType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('seeds a complete demo dataset without errors', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'admin@eventflow.test')->first()?->role)
        ->toBe(UserRole::SystemAdmin);

    expect(Organizer::query()->where('slug', 'pulse-collective')->exists())->toBeTrue();
    expect(Organizer::query()->where('slug', 'northstar-conferences')->exists())->toBeTrue();

    expect(Event::query()->count())->toBeGreaterThanOrEqual(14);
    expect(TicketType::query()->count())->toBeGreaterThan(0);
});

it('is idempotent — running the seeder twice does not duplicate rows', function () {
    $this->seed(DatabaseSeeder::class);
    $userCount = User::query()->count();
    $eventCount = Event::query()->count();

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe($userCount);
    expect(Event::query()->count())->toBe($eventCount);
});
