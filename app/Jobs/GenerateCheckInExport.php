<?php

namespace App\Jobs;

use App\Models\CheckIn;
use App\Models\Export;
use App\Models\RegistrationAttendee;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateCheckInExport implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly int $exportId)
    {
        $this->onQueue('exports');
    }

    public function handle(): void
    {
        /** @var Export $export */
        $export = Export::query()->findOrFail($this->exportId);
        if ($export->status === 'completed') {
            return;
        }

        $export->status = 'processing';
        $export->save();

        try {
            $filters = $export->filters ?? [];
            $query = CheckIn::query()
                ->whereHas('event', fn ($q) => $q->where('organizer_id', $export->organizer_id))
                ->with(['event', 'ticket', 'registration.attendees', 'staff']);

            if (isset($filters['event_id'])) {
                $query->where('event_id', $filters['event_id']);
            }

            $filename = 'check-ins-'.$export->uuid.'.csv';
            $path = 'exports/'.$filename;
            $handle = fopen('php://temp', 'w+');
            if ($handle === false) {
                throw new \RuntimeException('Could not open temporary stream for CSV writing.');
            }

            fputcsv($handle, [
                'event_title', 'ticket_token', 'registration_reference',
                'attendee_name', 'attendee_email', 'method',
                'scanned_at', 'staff_name', 'reversed', 'reversed_reason',
            ]);

            $rowCount = 0;
            $query->orderBy('scanned_at')->chunkById(500, function ($chunk) use ($handle, &$rowCount): void {
                foreach ($chunk as $checkIn) {
                    $attendee = $checkIn->registration->attendees->first();
                    $attendeeName = $attendee instanceof RegistrationAttendee ? $attendee->name : '';
                    $attendeeEmail = $attendee instanceof RegistrationAttendee ? $attendee->email : '';
                    $staff = $checkIn->staff;
                    $staffName = $staff !== null ? $staff->name : '';

                    fputcsv($handle, [
                        $checkIn->event->title,
                        $checkIn->ticket->public_token,
                        $checkIn->registration->reference,
                        $attendeeName,
                        $attendeeEmail,
                        $checkIn->method->value,
                        $checkIn->scanned_at->toIso8601String(),
                        $staffName,
                        $checkIn->isReversed() ? 'yes' : 'no',
                        $checkIn->reversed_reason ?? '',
                    ]);
                    $rowCount++;
                }
            });

            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);

            Storage::disk('local')->put($path, (string) $csv);

            $export->status = 'completed';
            $export->file_path = $path;
            $export->filename = $filename;
            $export->row_count = $rowCount;
            $export->completed_at = now();
            $export->save();
        } catch (Throwable $e) {
            $export->status = 'failed';
            $export->failure_reason = $e->getMessage();
            $export->save();
            throw $e;
        }
    }
}
