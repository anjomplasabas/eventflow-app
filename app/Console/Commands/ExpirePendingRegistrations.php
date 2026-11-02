<?php

namespace App\Console\Commands;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePendingRegistrations extends Command
{
    protected $signature = 'eventflow:expire-pending-registrations {--minutes=30 : Age threshold in minutes}';

    protected $description = 'Transition abandoned payment_pending registrations to expired.';

    public function handle(AuditLogger $audit): int
    {
        $threshold = now()->subMinutes((int) $this->option('minutes'));
        $expired = 0;

        Registration::query()
            ->where('status', RegistrationStatus::PaymentPending)
            ->where('created_at', '<', $threshold)
            ->chunkById(100, function ($chunk) use ($audit, &$expired): void {
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

                    $expired++;
                }
            });

        $this->info("Expired {$expired} pending registration(s).");

        return self::SUCCESS;
    }
}
