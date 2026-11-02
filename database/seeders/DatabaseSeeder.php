<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Enums\OrganizerMemberRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->makeUser('System Administrator', 'admin@eventflow.test', UserRole::SystemAdmin);
        $ownerAlice = $this->makeUser('Alice Harper', 'alice@acme.test', UserRole::OrganizerOwner);
        $staffBob = $this->makeUser('Bob Kim', 'bob@acme.test', UserRole::OrganizerStaff);
        $ownerCarla = $this->makeUser('Carla Zhou', 'carla@luma.test', UserRole::OrganizerOwner);
        $this->makeUser('Dana Lee', 'dana@attendee.test', UserRole::Attendee);
        unset($admin);

        $acme = Organizer::query()->firstOrCreate(
            ['slug' => 'acme-events'],
            [
                'name' => 'Acme Events',
                'contact_email' => 'hello@acme.test',
                'contact_phone' => '+1-555-0100',
            ],
        );
        $luma = Organizer::query()->firstOrCreate(
            ['slug' => 'luma-collective'],
            [
                'name' => 'Luma Collective',
                'contact_email' => 'hi@luma.test',
                'contact_phone' => null,
            ],
        );

        $this->attach($ownerAlice, $acme, OrganizerMemberRole::Owner);
        $this->attach($staffBob, $acme, OrganizerMemberRole::Staff);
        $this->attach($ownerCarla, $luma, OrganizerMemberRole::Owner);

        $this->seedEvents($acme);
        $this->seedEvents($luma, offset: 7);
    }

    private function makeUser(string $name, string $email, UserRole $role): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'role' => $role,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ],
        );
    }

    private function attach(User $user, Organizer $organizer, OrganizerMemberRole $role): void
    {
        $user->organizers()->syncWithoutDetaching([
            $organizer->id => [
                'role' => $role->value,
                'invited_at' => now(),
                'accepted_at' => now(),
            ],
        ]);
    }

    private function seedEvents(Organizer $organizer, int $offset = 0): void
    {
        $specs = [
            [
                'slug' => 'kickoff-conference',
                'title' => 'Kickoff Conference',
                'status' => EventStatus::Published,
                'starts' => now()->addDays(14 + $offset),
                'capacity' => 200,
            ],
            [
                'slug' => 'workshop-advanced-laravel',
                'title' => 'Workshop · Advanced Laravel',
                'status' => EventStatus::Published,
                'starts' => now()->addDays(30 + $offset),
                'capacity' => 40,
            ],
            [
                'slug' => 'sold-out-meetup',
                'title' => 'Sold-out Meetup',
                'status' => EventStatus::SoldOut,
                'starts' => now()->addDays(10 + $offset),
                'capacity' => 50,
            ],
            [
                'slug' => 'webinar-architecture',
                'title' => 'Webinar · Architecture Patterns',
                'status' => EventStatus::Scheduled,
                'starts' => now()->addDays(45 + $offset),
                'capacity' => null,
            ],
            [
                'slug' => 'private-leadership-dinner',
                'title' => 'Private Leadership Dinner',
                'status' => EventStatus::Published,
                'visibility' => EventVisibility::Private,
                'access_code' => 'LEAD-2026',
                'starts' => now()->addDays(21 + $offset),
                'capacity' => 25,
            ],
            [
                'slug' => 'last-years-summit',
                'title' => "Last Year's Summit",
                'status' => EventStatus::Completed,
                'starts' => now()->subDays(60 + $offset),
                'capacity' => 300,
            ],
            [
                'slug' => 'cancelled-roadshow',
                'title' => 'Cancelled Roadshow',
                'status' => EventStatus::Cancelled,
                'starts' => now()->addDays(20 + $offset),
                'capacity' => 100,
            ],
            [
                'slug' => 'draft-planning-event',
                'title' => 'Draft · Planning Event',
                'status' => EventStatus::Draft,
                'starts' => now()->addDays(60 + $offset),
                'capacity' => 80,
            ],
        ];

        foreach ($specs as $spec) {
            Event::query()->updateOrCreate(
                ['organizer_id' => $organizer->id, 'slug' => $spec['slug']],
                [
                    'title' => $spec['title'],
                    'description' => 'Seeded demo event for '.$organizer->name.'.',
                    'category' => 'Demo',
                    'status' => $spec['status'],
                    'visibility' => $spec['visibility'] ?? EventVisibility::Public,
                    'access_code' => $spec['access_code'] ?? null,
                    'starts_at' => $spec['starts'],
                    'ends_at' => $spec['starts']->copy()->addHours(2),
                    'timezone' => 'UTC',
                    'registration_opens_at' => $spec['starts']->copy()->subDays(14),
                    'registration_closes_at' => $spec['starts']->copy()->subDay(),
                    'capacity' => $spec['capacity'],
                    'per_attendee_limit' => 1,
                ],
            );
        }
    }
}
