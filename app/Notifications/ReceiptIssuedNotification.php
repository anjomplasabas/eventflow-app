<?php

namespace App\Notifications;

use App\Models\Receipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReceiptIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Receipt $receipt)
    {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $event = $this->receipt->event_snapshot;
        $title = (string) ($event['title'] ?? 'your event');

        return (new MailMessage)
            ->subject("Your receipt: {$title}")
            ->markdown('mail.receipts.issued', [
                'receipt' => $this->receipt,
            ]);
    }
}
