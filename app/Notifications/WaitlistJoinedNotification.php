<?php

namespace App\Notifications;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WaitlistJoinedNotification extends Notification implements ShouldQueue
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

        return (new MailMessage)
            ->subject("You're on the waitlist: {$event->title}")
            ->markdown('mail.registrations.waitlisted', [
                'registration' => $this->registration,
                'event' => $event,
                'organizer' => $event->organizer,
            ]);
    }
}
