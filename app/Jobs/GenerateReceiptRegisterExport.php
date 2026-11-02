<?php

namespace App\Jobs;

use App\Models\Export;
use App\Models\Receipt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateReceiptRegisterExport implements ShouldQueue
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
            $query = Receipt::query()
                ->whereHas('registration.event', function ($q) use ($export): void {
                    $q->where('organizer_id', $export->organizer_id);
                })
                ->with('registration.event');

            $filters = $export->filters ?? [];
            if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
                $query->where('status', $filters['status']);
            }
            if (isset($filters['issued_from']) && is_string($filters['issued_from'])) {
                $query->where('issued_at', '>=', $filters['issued_from']);
            }
            if (isset($filters['issued_to']) && is_string($filters['issued_to'])) {
                $query->where('issued_at', '<=', $filters['issued_to']);
            }

            $filename = 'receipt-register-'.$export->uuid.'.csv';
            $path = 'exports/'.$filename;
            $handle = fopen('php://temp', 'w+');
            if ($handle === false) {
                throw new \RuntimeException('Could not open temporary stream for CSV writing.');
            }

            fputcsv($handle, [
                'receipt_number',
                'registration_reference',
                'event_title',
                'attendee_name',
                'attendee_email',
                'currency',
                'subtotal',
                'discount',
                'tax',
                'fee',
                'total',
                'status',
                'issued_at',
            ]);

            $rowCount = 0;
            $query->orderBy('issued_at')->chunkById(500, function ($chunk) use ($handle, &$rowCount): void {
                foreach ($chunk as $receipt) {
                    fputcsv($handle, [
                        $receipt->public_number,
                        (string) ($receipt->attendee_snapshot['registration_reference'] ?? ''),
                        (string) ($receipt->event_snapshot['title'] ?? ''),
                        (string) ($receipt->attendee_snapshot['name'] ?? ''),
                        (string) ($receipt->attendee_snapshot['email'] ?? ''),
                        $receipt->currency,
                        $this->minorToDecimal($receipt->subtotal_minor),
                        $this->minorToDecimal($receipt->discount_minor),
                        $this->minorToDecimal($receipt->tax_minor),
                        $this->minorToDecimal($receipt->fee_minor),
                        $this->minorToDecimal($receipt->total_minor),
                        $receipt->status->value,
                        $receipt->issued_at->toIso8601String(),
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

    private function minorToDecimal(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }
}
