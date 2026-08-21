<div>
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="subtle" square aria-label="Benachrichtigungen" class="relative text-[#073070]! dark:text-white!">
            <flux:icon.bell variant="mini" />
            @if($this->unreadCount > 0)
                <span class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">
                    {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
                </span>
            @endif
        </flux:button>

        <flux:menu class="w-96 max-w-[90vw]">
            <div class="flex items-center justify-between px-3 py-2">
                <flux:heading size="sm">Benachrichtigungen</flux:heading>
                @if($this->unreadCount > 0)
                    <flux:button size="xs" variant="ghost" wire:click="markAllAsRead">
                        Alle gelesen
                    </flux:button>
                @endif
            </div>

            <flux:menu.separator />

            @forelse($this->recentNotifications as $notification)
                @php
                    $data = $notification->data;
                @endphp
                <flux:menu.item
                    :href="$data['url'] ?? '#'"
                    wire:navigate
                    wire:click="markAsRead('{{ $notification->id }}')"
                    class="font-semibold"
                >
                    <div class="min-w-0">
                        <div class="truncate">{{ $data['title'] ?? 'Benachrichtigung' }}</div>
                        <div class="truncate text-xs text-zinc-500">{{ $data['body'] ?? '' }}</div>
                    </div>
                </flux:menu.item>
            @empty
                <div class="px-3 py-4 text-sm text-zinc-500">Keine ungelesenen Benachrichtigungen.</div>
            @endforelse

            <flux:menu.separator />

            <flux:menu.item :href="route('settings.notifications')" wire:navigate icon="cog">
                Einstellungen
            </flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</div>
