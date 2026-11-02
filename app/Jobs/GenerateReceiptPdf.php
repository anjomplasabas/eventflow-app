<?php

namespace App\Jobs;

use App\Contracts\Receipts\ReceiptRenderer;
use App\Models\Receipt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateReceiptPdf implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly int $receiptId)
    {
        $this->onQueue('documents');
    }

    public function handle(ReceiptRenderer $renderer): void
    {
        $receipt = Receipt::query()->with('items')->findOrFail($this->receiptId);
        if ($receipt->pdf_path !== null && Storage::disk('local')->exists($receipt->pdf_path)) {
            return; // Idempotent
        }

        $bytes = $renderer->render($receipt);
        $path = 'receipts/'.$receipt->public_number.'.pdf';
        Storage::disk('local')->put($path, $bytes);

        $receipt->pdf_path = $path;
        $receipt->save();
    }
}
