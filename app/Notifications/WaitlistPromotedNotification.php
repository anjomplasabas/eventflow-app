<?php

namespace App\Notifications;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WaitlistPromotedNotification extends Notification implements ShouldQueue
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
        $isConfirmed = $this->registration->status === RegistrationStatus::Confirmed;

        return (new MailMessage)
            ->subject(($isConfirmed ? "You're in: " : 'Seat available: ').$event->title)
            ->markdown('mail.registrations.promoted', [
                'registration' => $this->registration,
                'event' => $event,
                'organizer' => $event->organizer,
                'isConfirmed' => $isConfirmed,
            ]);
    }
}
