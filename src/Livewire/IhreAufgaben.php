<?php

namespace Hwkdo\IntranetAppBase\Livewire;

use Hwkdo\IntranetAppBase\Services\TaskService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class IhreAufgaben extends Component
{
    #[Computed]
    public function groupedTasks(): Collection
    {
        return app(TaskService::class)->getTasksGroupedByApp(Auth::user());
    }

    #[Computed]
    public function totalCount(): int
    {
        return $this->groupedTasks->flatten()->count();
    }

    #[Computed]
    public function hideWhenEmpty(): bool
    {
        $user = Auth::user();

        return (bool) ($user->settings->dashboard->hideAufgabenWhenEmpty ?? false);
    }

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $userId = Auth::id();

        if ($userId === null) {
            return [];
        }

        return [
            "echo-private:App.Models.User.{$userId},.ticket.updated" => 'refreshTasks',
        ];
    }

    public function refreshTasks(): void
    {
        unset($this->groupedTasks, $this->totalCount);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('intranet-app-base::livewire.ihre-aufgaben');
    }
}
