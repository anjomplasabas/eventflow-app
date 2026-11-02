<?php

namespace App\Jobs;

use App\Models\Export;
use App\Models\Registration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateAttendeeExport implements ShouldQueue
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
            $query = Registration::query()
                ->whereHas('event', fn ($q) => $q->where('organizer_id', $export->organizer_id))
                ->with(['event', 'ticketType', 'attendees']);

            if (isset($filters['event_id'])) {
                $query->where('event_id', $filters['event_id']);
            }

            $filename = 'attendees-'.$export->uuid.'.csv';
            $path = 'exports/'.$filename;
            $handle = fopen('php://temp', 'w+');
            if ($handle === false) {
                throw new \RuntimeException('Could not open temporary stream for CSV writing.');
            }

            fputcsv($handle, [
                'registration_reference', 'event_title', 'ticket_type',
                'attendee_name', 'attendee_email', 'phone',
                'quantity', 'status', 'registered_at',
            ]);

            $rowCount = 0;
            $query->orderBy('created_at')->chunkById(500, function ($chunk) use ($handle, &$rowCount): void {
                foreach ($chunk as $registration) {
                    foreach ($registration->attendees as $attendee) {
                        fputcsv($handle, [
                            $registration->reference,
                            $registration->event->title,
                            $registration->ticketType->name,
                            $attendee->name,
                            $attendee->email,
                            $attendee->phone ?? '',
                            $registration->quantity,
                            $registration->status->value,
                            $registration->created_at?->toIso8601String() ?? '',
                        ]);
                        $rowCount++;
                    }
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
