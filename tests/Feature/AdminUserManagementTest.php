<?php

use App\Enums\OrganizerMemberRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Organizer;
use App\Models\User;

function admin(): User
{
    return User::factory()->create(['role' => UserRole::SystemAdmin]);
}

it('denies non-admins from the admin users page', function () {
    $attendee = User::factory()->create();

    $this->actingAs($attendee)
        ->get('/admin/users')
        ->assertForbidden();
});

it('redirects unauthenticated requests to login', function () {
    $this->get('/admin/users')
        ->assertRedirect('/login');
});

it('lets a system admin view the users page', function () {
    $this->actingAs(admin())
        ->get('/admin/users')
        ->assertOk();
});

it('promotes a user by updating their global role', function () {
    $target = User::factory()->create();

    $this->actingAs(admin())
        ->patch("/admin/users/{$target->id}/role", ['role' => UserRole::OrganizerOwner->value])
        ->assertRedirect('/admin/users');

    expect($target->fresh()->role)->toBe(UserRole::OrganizerOwner);
    expect(AuditLog::query()->where('action', 'user.role_updated')->exists())->toBeTrue();
});

it('attaches an organizer membership and lifts role from attendee automatically', function () {
    $target = User::factory()->create(); // defaults to attendee
    $organizer = Organizer::factory()->create();

    $this->actingAs(admin())
        ->post("/admin/users/{$target->id}/memberships", [
            'organizer_slug' => $organizer->slug,
            'role' => OrganizerMemberRole::Owner->value,
        ])
        ->assertRedirect('/admin/users');

    expect($target->fresh()->ownsOrganizer($organizer))->toBeTrue()
        ->and($target->fresh()->role)->toBe(UserRole::OrganizerOwner);
});

it('rejects attach when the organizer slug does not exist', function () {
    $target = User::factory()->create();

    $this->actingAs(admin())
        ->from('/admin/users')
        ->post("/admin/users/{$target->id}/memberships", [
            'organizer_slug' => 'does-not-exist',
            'role' => OrganizerMemberRole::Owner->value,
        ])
        ->assertSessionHasErrors('organizer_slug');
});

it('detaches an organizer membership', function () {
    $target = User::factory()->create();
    $organizer = Organizer::factory()->create();
    $target->organizers()->attach($organizer, [
        'role' => OrganizerMemberRole::Owner->value,
        'invited_at' => now(),
        'accepted_at' => now(),
    ]);

    $this->actingAs(admin())
        ->delete("/admin/users/{$target->id}/memberships/{$organizer->slug}")
        ->assertRedirect('/admin/users');

    expect($target->fresh()->belongsToOrganizer($organizer))->toBeFalse();
});

it('updates a user status to suspended', function () {
    $target = User::factory()->create();

    $this->actingAs(admin())
        ->patch("/admin/users/{$target->id}/status", ['status' => UserStatus::Suspended->value])
        ->assertRedirect('/admin/users');

    expect($target->fresh()->status)->toBe(UserStatus::Suspended);
});
