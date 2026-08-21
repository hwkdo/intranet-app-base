<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Livewire;

use Flux\Flux;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationBell extends Component
{
    public function getListeners(): array
    {
        $userId = Auth::id();

        if (! $userId) {
            return [];
        }

        return [
            "echo-private:App.Models.User.{$userId},.inbox.notification.received" => 'onNotificationReceived',
        ];
    }

    public function onNotificationReceived(array $data): void
    {
        unset($this->unreadCount, $this->recentNotifications);

        Flux::toast(
            heading: $data['title'] ?? 'Neue Benachrichtigung',
            text: $data['body'] ?? '',
            variant: 'info',
        );
    }

    public function markAsRead(string $notificationId): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        /** @var DatabaseNotification|null $notification */
        $notification = $user->notifications()->where('id', $notificationId)->first();

        $notification?->markAsRead();

        unset($this->unreadCount, $this->recentNotifications);
    }

    public function markAllAsRead(): void
    {
        Auth::user()?->unreadNotifications->markAsRead();

        unset($this->unreadCount, $this->recentNotifications);
    }

    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()?->unreadNotifications()->count() ?? 0;
    }

    #[Computed]
    public function recentNotifications(): \Illuminate\Support\Collection
    {
        return Auth::user()?->unreadNotifications()->limit(10)->get() ?? collect();
    }

    public function render()
    {
        return view('intranet-app-base::livewire.notification-bell');
    }
}
