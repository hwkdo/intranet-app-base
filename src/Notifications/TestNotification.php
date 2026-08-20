<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Notifications;

use Hwkdo\IntranetAppBase\Enums\NotificationChannelKey;
use Hwkdo\IntranetAppBase\Services\NotificationPreferenceResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

class TestNotification extends Notification
{
    /** @param list<string> $channels */
    public function __construct(
        private readonly array $channels,
    ) {}

    public function via(object $notifiable): array
    {
        $resolver = app(NotificationPreferenceResolver::class);
        $mapped = [];

        foreach ($this->channels as $channelKey) {
            $laravelChannel = $resolver->mapToLaravelChannel($channelKey);

            if ($laravelChannel !== null) {
                $mapped[] = $laravelChannel;
            }
        }

        return $mapped;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Testbenachrichtigung – Intranet')
            ->greeting('Test erfolgreich!')
            ->line('Dies ist eine Testbenachrichtigung aus den Intranet-Einstellungen.')
            ->line('Wenn Sie diese E-Mail lesen, funktioniert der E-Mail-Kanal korrekt.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type_key' => '_test',
            'title' => 'Testbenachrichtigung',
            'body' => 'Dies ist eine Testbenachrichtigung. Der Inbox-Kanal funktioniert korrekt.',
            'url' => null,
            'app_identifier' => null,
        ];
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Testbenachrichtigung')
            ->body('Web-Push funktioniert! Diese Nachricht wurde aus den Intranet-Einstellungen gesendet.')
            ->icon('/img/Handwerkskammer-Dortmund-Logo-Header.png');
    }

    public function toTeams(object $notifiable): array
    {
        return [
            'preview' => 'Dies ist eine Testbenachrichtigung aus den Intranet-Einstellungen.',
            'topic' => 'Testbenachrichtigung',
            'url' => null,
        ];
    }
}
