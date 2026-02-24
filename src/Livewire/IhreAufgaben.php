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

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('intranet-app-base::livewire.ihre-aufgaben');
    }
}
