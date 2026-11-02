<?php

namespace App\Actions\CheckIns;

use App\Enums\RegistrationStatus;
use App\Models\CheckIn;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReverseCheckIn
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(CheckIn $checkIn, User $actor, string $reason): CheckIn
    {
        if (trim($reason) === '') {
            throw new DomainException('Reversal reason is required.');
        }

        return DB::transaction(function () use ($checkIn, $actor, $reason): CheckIn {
            if ($checkIn->isReversed()) {
                return $checkIn;
            }

            $checkIn->reversed_at = now();
            $checkIn->reversed_by = $actor->id;
            $checkIn->reversed_reason = $reason;
            $checkIn->save();

            $ticket = $checkIn->ticket;
            $ticket->checked_in_at = null;
            $ticket->save();

            $registration = $checkIn->registration;
            if ($registration->status === RegistrationStatus::CheckedIn) {
                $registration->status = RegistrationStatus::Confirmed;
                $registration->checked_in_at = null;
                $registration->save();
            }

            $this->audit->record('check_in.reversed', $checkIn, after: [
                'reason' => $reason,
            ], actor: $actor);

            return $checkIn;
        });
    }
}
