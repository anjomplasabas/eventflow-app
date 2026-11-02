<?php

namespace App\Jobs;

use App\Contracts\Receipts\ReceiptRenderer;
use App\Models\Export;
use App\Models\Receipt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

class GenerateReceiptZipExport implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly int $exportId)
    {
        $this->onQueue('exports');
    }

    public function handle(ReceiptRenderer $renderer): void
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
                ->with(['items', 'registration.event']);

            $filters = $export->filters ?? [];
            if (isset($filters['event_id'])) {
                $query->whereHas('registration', function ($q) use ($filters): void {
                    $q->where('event_id', $filters['event_id']);
                });
            }

            $filename = 'receipts-'.$export->uuid.'.zip';
            $path = 'exports/'.$filename;
            $tmpPath = tempnam(sys_get_temp_dir(), 'rzip');
            if ($tmpPath === false) {
                throw new \RuntimeException('Could not create temp file for zip.');
            }

            $zip = new ZipArchive;
            if ($zip->open($tmpPath, ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Failed to open zip for writing.');
            }

            $rowCount = 0;
            $query->orderBy('issued_at')->chunkById(100, function ($chunk) use ($renderer, $zip, &$rowCount): void {
                foreach ($chunk as $receipt) {
                    $bytes = $renderer->render($receipt);
                    $zip->addFromString($receipt->public_number.'.pdf', $bytes);
                    $rowCount++;
                }
            });

            $zip->close();

            $contents = file_get_contents($tmpPath);
            if ($contents !== false) {
                Storage::disk('local')->put($path, $contents);
            }
            @unlink($tmpPath);

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
