<?php

namespace App\Notifications;

use App\Models\Refund;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Refund $refund)
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
        $payment = $this->refund->payment;
        $registration = $payment->registration;
        $event = $registration->event;

        return (new MailMessage)
            ->subject("Refund issued: {$event->title}")
            ->markdown('mail.registrations.refunded', [
                'refund' => $this->refund,
                'payment' => $payment,
                'registration' => $registration,
                'event' => $event,
                'organizer' => $event->organizer,
            ]);
    }
}
