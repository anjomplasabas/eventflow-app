<?php

namespace App\Notifications;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Registration $registration,
        public readonly ?string $reason = null,
    ) {
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
        $event = $this->registration->event;
        $organizer = $event->organizer;

        return (new MailMessage)
            ->subject("Registration cancelled: {$event->title}")
            ->markdown('mail.registrations.cancelled', [
                'registration' => $this->registration,
                'event' => $event,
                'organizer' => $organizer,
                'reason' => $this->reason,
            ]);
    }
}
