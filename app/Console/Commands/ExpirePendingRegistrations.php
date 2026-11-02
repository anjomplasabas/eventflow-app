<?php

namespace App\Console\Commands;

use App\Actions\Registrations\PromoteWaitlist;
use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Models\TicketType;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePendingRegistrations extends Command
{
    protected $signature = 'eventflow:expire-pending-registrations {--minutes=30 : Age threshold in minutes}';

    protected $description = 'Transition abandoned payment_pending registrations to expired.';

    public function handle(AuditLogger $audit, PromoteWaitlist $promote): int
    {
        $threshold = now()->subMinutes((int) $this->option('minutes'));
        $expired = 0;
        $freedTicketTypeIds = [];

        Registration::query()
            ->where('status', RegistrationStatus::PaymentPending)
            ->where('created_at', '<', $threshold)
            ->chunkById(100, function ($chunk) use ($audit, &$expired, &$freedTicketTypeIds): void {
                foreach ($chunk as $registration) {
                    if (! $registration->status->canTransitionTo(RegistrationStatus::Expired)) {
                        continue;
                    }

                    DB::transaction(function () use ($registration, $audit): void {
                        $before = ['status' => $registration->status->value];
                        $registration->status = RegistrationStatus::Expired;
                        $registration->save();
                        $audit->record('registration.expired', $registration, before: $before, after: [
                            'status' => $registration->status->value,
                        ]);
                    });

                    $freedTicketTypeIds[$registration->ticket_type_id] = true;
                    $expired++;
                }
            });

        foreach (array_keys($freedTicketTypeIds) as $ticketTypeId) {
            $ticketType = TicketType::query()->find($ticketTypeId);
            if ($ticketType !== null) {
                $promote($ticketType);
            }
        }

        $this->info("Expired {$expired} pending registration(s).");

        return self::SUCCESS;
    }
}
