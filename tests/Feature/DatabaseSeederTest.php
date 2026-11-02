<?php

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('seeds a complete demo dataset without errors', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'admin@eventflow.test')->first()?->role)
        ->toBe(UserRole::SystemAdmin);

    expect(Organizer::query()->where('slug', 'acme-events')->exists())->toBeTrue();
    expect(Organizer::query()->where('slug', 'luma-collective')->exists())->toBeTrue();

    expect(Event::query()->count())->toBeGreaterThanOrEqual(16);
});

it('is idempotent — running the seeder twice does not duplicate rows', function () {
    $this->seed(DatabaseSeeder::class);
    $userCount = User::query()->count();
    $eventCount = Event::query()->count();

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe($userCount);
    expect(Event::query()->count())->toBe($eventCount);
});
