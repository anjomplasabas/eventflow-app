<?php

namespace App\Jobs;

use App\Models\Ticket;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateTicketQrCode implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly int $ticketId)
    {
        $this->onQueue('documents');
    }

    public function handle(): void
    {
        $ticket = Ticket::query()->findOrFail($this->ticketId);
        if ($ticket->qr_image_path !== null && Storage::disk('local')->exists($ticket->qr_image_path)) {
            return;
        }

        $url = url('/verify/tickets/'.$ticket->public_token);
        $builder = new Builder(
            writer: new PngWriter,
            data: $url,
            size: 360,
            margin: 8,
        );
        $result = $builder->build();

        $path = 'tickets/'.$ticket->public_token.'.png';
        Storage::disk('local')->put($path, $result->getString());

        $ticket->qr_image_path = $path;
        $ticket->save();
    }
}
