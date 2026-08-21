<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Support;

use Illuminate\Database\Eloquent\Model;

final class ActiveNotifiable
{
    /**
     * Only users with active = true should receive intranet notifications.
     * Notifiables without an active flag are allowed (non-user recipients).
     */
    public static function matches(object $notifiable): bool
    {
        if (! $notifiable instanceof Model) {
            return ! isset($notifiable->active) || (bool) $notifiable->active;
        }

        if (array_key_exists('active', $notifiable->getAttributes())) {
            return (bool) $notifiable->getAttribute('active');
        }

        if ($notifiable->getKey() === null) {
            return true;
        }

        return $notifiable->newQuery()
            ->whereKey($notifiable->getKey())
            ->where('active', true)
            ->exists();
    }
}
