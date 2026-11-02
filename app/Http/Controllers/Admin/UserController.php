<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizerMemberRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->with('organizers:id,slug,name')
            ->orderBy('id')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'status' => $user->status->value,
                'memberships' => $user->organizers->map(fn (Organizer $organizer) => [
                    'slug' => $organizer->slug,
                    'name' => $organizer->name,
                    'role' => $organizer->getAttribute('pivot')?->getAttribute('role'),
                ])->values(),
            ]);

        $organizers = Organizer::query()
            ->orderBy('name')
            ->get(['id', 'slug', 'name']);

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'organizers' => $organizers,
            'roles' => array_map(fn (UserRole $r) => ['value' => $r->value, 'label' => $r->value], UserRole::cases()),
            'member_roles' => array_map(fn (OrganizerMemberRole $r) => ['value' => $r->value, 'label' => $r->value], OrganizerMemberRole::cases()),
        ]);
    }

    public function updateRole(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $before = ['role' => $user->role->value];
        $user->role = UserRole::from($data['role']);
        $user->save();
        $after = ['role' => $user->role->value];

        $audit->record('user.role_updated', $user, before: $before, after: $after);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return redirect()->route('admin.users.index');
    }

    public function updateStatus(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(UserStatus::class)],
        ]);

        $before = ['status' => $user->status->value];
        $user->status = UserStatus::from($data['status']);
        $user->save();
        $after = ['status' => $user->status->value];

        $audit->record('user.status_updated', $user, before: $before, after: $after);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status updated.')]);

        return redirect()->route('admin.users.index');
    }

    public function attachOrganizer(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'organizer_slug' => ['required', 'exists:organizers,slug'],
            'role' => ['required', Rule::enum(OrganizerMemberRole::class)],
        ]);

        $organizer = Organizer::query()->where('slug', $data['organizer_slug'])->firstOrFail();

        $user->organizers()->syncWithoutDetaching([
            $organizer->id => [
                'role' => $data['role'],
                'invited_at' => now(),
                'accepted_at' => now(),
            ],
        ]);

        // Promote the global role if an attendee becomes an organizer.
        if ($user->role === UserRole::Attendee) {
            $user->role = $data['role'] === OrganizerMemberRole::Owner->value
                ? UserRole::OrganizerOwner
                : UserRole::OrganizerStaff;
            $user->save();
        }

        $audit->record('user.organizer_attached', $user, after: [
            'organizer_id' => $organizer->id,
            'organizer_slug' => $organizer->slug,
            'role' => $data['role'],
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Membership added.')]);

        return redirect()->route('admin.users.index');
    }

    public function detachOrganizer(Request $request, User $user, Organizer $organizer, AuditLogger $audit): RedirectResponse
    {
        $user->organizers()->detach($organizer->id);

        $audit->record('user.organizer_detached', $user, before: [
            'organizer_id' => $organizer->id,
            'organizer_slug' => $organizer->slug,
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Membership removed.')]);

        return redirect()->route('admin.users.index');
    }
}
