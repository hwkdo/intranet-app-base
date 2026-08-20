<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Notifications;

use Hwkdo\IntranetAppBase\Services\NotificationPreferenceResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

abstract class IntranetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    abstract public function typeKey(): string;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return app(NotificationPreferenceResolver::class)
            ->viaChannels($notifiable, $this->typeKey());
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return app(NotificationPreferenceResolver::class)
            ->shouldSendOnChannel($notifiable, $this->typeKey(), $channel);
    }

    /**
     * @return array<string, mixed>
     */
    protected function inboxPayload(
        string $title,
        string $body,
        ?string $url = null,
        ?string $appIdentifier = null,
    ): array {
        return [
            'type_key' => $this->typeKey(),
            'app_identifier' => $appIdentifier,
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ];
    }
}
