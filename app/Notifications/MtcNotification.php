<?php

namespace App\Notifications;

use App\Domain\Notifications\NotificationTypes;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for all user notifications: channels follow the user's preferences
 * (NotificationTypes), content is a short title + body + link.
 */
abstract class MtcNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract public function type(): string;

    abstract public function title(): string;

    abstract public function body(): string;

    abstract public function url(): string;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? NotificationTypes::channelsFor($notifiable, $this->type()) : [];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return ['type' => $this->type(), 'title' => $this->title(), 'body' => $this->body(), 'url' => $this->url()];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title().' – MáToCenu.cz')
            ->greeting('Ahoj!')
            ->line($this->body())
            ->action('Zobrazit', $this->url())
            ->line('Upozornění si můžeš kdykoliv vypnout v Nastavení → Upozornění.')
            ->salutation('Tým MáToCenu.cz');
    }
}
