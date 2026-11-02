<?php

namespace App\Console\Commands;

use App\Models\Export;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DeleteExpiredExports extends Command
{
    protected $signature = 'eventflow:delete-expired-exports';

    protected $description = 'Delete export files that have passed their expires_at. Keeps the audit row.';

    public function handle(): int
    {
        $deleted = 0;

        Export::query()
            ->where('expires_at', '<', now())
            ->whereNotNull('file_path')
            ->chunkById(100, function ($chunk) use (&$deleted): void {
                foreach ($chunk as $export) {
                    if ($export->file_path !== null && Storage::disk('local')->exists($export->file_path)) {
                        Storage::disk('local')->delete($export->file_path);
                    }
                    $export->file_path = null;
                    $export->status = 'expired';
                    $export->save();
                    $deleted++;
                }
            });

        $this->info("Deleted {$deleted} expired export file(s).");

        return self::SUCCESS;
    }
}
