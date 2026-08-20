<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Notifications;

use Hwkdo\IntranetAppBase\Data\NotificationPayload;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class GenericIntranetNotification extends IntranetNotification
{
    public function __construct(
        private readonly string $notificationTypeKey,
        private readonly NotificationPayload $payload,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return $this->notificationTypeKey;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->payload->mailSubject ?? $this->payload->title)
            ->line($this->payload->body);

        if ($this->payload->url !== null) {
            $mail->action('Öffnen', $this->payload->url);
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inboxPayload(
            $this->payload->title,
            $this->payload->body,
            $this->payload->url,
            $this->payload->appIdentifier,
        );
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        $message = (new WebPushMessage)
            ->title($this->payload->title)
            ->body($this->payload->body)
            ->icon('/img/Handwerkskammer-Dortmund-Logo-Header.png');

        if ($this->payload->url !== null) {
            $message->data(['url' => $this->payload->url]);
        }

        return $message;
    }

    public function toTeams(object $notifiable): array
    {
        return [
            'preview' => $this->payload->body,
            'topic' => $this->payload->teamsTopic ?? $this->payload->title,
            'url' => $this->payload->url,
        ];
    }
}
