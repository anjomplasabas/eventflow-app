<?php

namespace App\Actions\Registrations;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CreateGuestRegistration
{
    public function __construct(private readonly CreateRegistration $createRegistration) {}

    /**
     * Create an attendee account on the fly, register them for the event,
     * log them in so they land on the confirmation page as themselves, and
     * dispatch a password-set email so they can sign back in later.
     *
     * @param  array{name: string, email: string, phone: string|null}  $attendee
     */
    public function __invoke(
        Event $event,
        TicketType $ticketType,
        array $attendee,
        int $quantity = 1,
        ?string $idempotencyKey = null,
    ): Registration {
        $user = DB::transaction(function () use ($attendee): User {
            $user = User::query()->create([
                'name' => $attendee['name'],
                'email' => $attendee['email'],
                'password' => Hash::make(Str::random(40)),
                'role' => UserRole::Attendee,
                'status' => UserStatus::Active,
                'email_verified_at' => null,
            ]);

            return $user;
        });

        Auth::login($user);

        $registration = ($this->createRegistration)(
            $event,
            $ticketType,
            $user,
            $attendee,
            $quantity,
            $idempotencyKey,
        );

        // Fortify sends a password-reset mail from this broker call, so the
        // guest can set a real password at their leisure.
        $broker = Password::broker();
        if ($broker instanceof PasswordBroker) {
            $broker->sendResetLink(['email' => $user->email]);
        }

        event(new PasswordReset($user));

        return $registration;
    }
}
