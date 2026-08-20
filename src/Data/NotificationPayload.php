<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

class NotificationPayload
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
        public readonly ?string $appIdentifier = null,
        public readonly ?string $mailSubject = null,
        public readonly ?string $teamsTopic = null,
    ) {}
}
