<?php

namespace App\Notifications;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Registration $registration)
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
        $event = $this->registration->event;
        $organizer = $event->organizer;

        return (new MailMessage)
            ->subject("You're registered: {$event->title}")
            ->markdown('mail.registrations.confirmed', [
                'registration' => $this->registration,
                'event' => $event,
                'organizer' => $organizer,
            ]);
    }
}
