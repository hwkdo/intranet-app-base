<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Livewire;

use Cron\CronExpression;
use Flux\Flux;
use Hwkdo\IntranetAppBase\Data\BackgroundActivityDefinition;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityKind;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityRunStatus;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityTrigger;
use Hwkdo\IntranetAppBase\Models\BackgroundActivityRun;
use Hwkdo\IntranetAppBase\Services\BackgroundActivityCatalog;
use Hwkdo\IntranetAppBase\Services\BackgroundActivityConfigStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;
use Livewire\Component;

class BackgroundActivities extends Component
{
    private const TYPE_KEY_DOT_PLACEHOLDER = '__';

    public ?string $appIdentifier = null;

    #[Url(as: 'kind', except: 'scheduled')]
    public string $kindFilter = 'scheduled';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** @var array<string, array<string, mixed>> */
    public array $drafts = [];

    public static function toWireKey(string $key): string
    {
        return str_replace('.', self::TYPE_KEY_DOT_PLACEHOLDER, $key);
    }

    public static function fromWireKey(string $wireKey): string
    {
        return str_replace(self::TYPE_KEY_DOT_PLACEHOLDER, '.', $wireKey);
    }

    public function mount(?string $appIdentifier = null): void
    {
        $this->appIdentifier = $appIdentifier !== null && $appIdentifier !== '' ? $appIdentifier : null;

        abort_unless($this->canView(), 403);

        if (! in_array($this->kindFilter, ['scheduled', 'queued', 'mail', 'notification'], true)) {
            $this->kindFilter = 'scheduled';
        }

        foreach ($this->scopedDefinitions() as $definition) {
            if ($definition->kind !== BackgroundActivityKind::Scheduled || $definition->defaultFrequency === null) {
                continue;
            }

            $frequency = app(BackgroundActivityConfigStore::class)->frequency($definition) ?? $definition->defaultFrequency;
            $parameters = [];

            foreach ($definition->parameters as $parameter) {
                $parameters[$parameter->key] = app(BackgroundActivityConfigStore::class)->parameter($definition->key, $parameter->key);
            }

            $this->drafts[self::toWireKey($definition->key)] = [
                'enabled' => app(BackgroundActivityConfigStore::class)->isEnabled($definition),
                'cadence' => $frequency->cadence->value,
                'every' => $frequency->every,
                'offset' => $frequency->offset,
                'at' => $frequency->at ?? '00:00',
                'day_of_week' => $frequency->dayOfWeek ?? 1,
                'parameters' => $parameters,
            ];
        }
    }

    public function save(string $wireKey): void
    {
        $definition = app(BackgroundActivityCatalog::class)->find(self::fromWireKey($wireKey));

        abort_unless($definition !== null && $this->canEdit($definition), 403);

        $draft = $this->drafts[$wireKey] ?? [];

        app(BackgroundActivityConfigStore::class)->save($definition, [
            'enabled' => (bool) ($draft['enabled'] ?? false),
            'cadence' => (string) ($draft['cadence'] ?? ''),
            'every' => (int) ($draft['every'] ?? 1),
            'offset' => (int) ($draft['offset'] ?? 0),
            'at' => (string) ($draft['at'] ?? '00:00'),
            'day_of_week' => (int) ($draft['day_of_week'] ?? 0),
            'parameters' => is_array($draft['parameters'] ?? null) ? $draft['parameters'] : [],
        ]);

        Flux::toast(text: 'Hintergrundaktivität gespeichert.', variant: 'success');
    }

    public function render()
    {
        $groups = $this->groups();

        return view('intranet-app-base::livewire.background-activities', [
            'groups' => $groups,
            'counts' => $this->kindCounts(),
            'showAppGroups' => count($groups) > 1,
            'uncataloguedMails' => $this->uncataloguedMails(),
            'failedNotifications' => $this->failedNotifications(),
            'weekdays' => range(0, 6),
        ]);
    }

    public function canView(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        if ($this->appIdentifier === null) {
            return $user->can('manage-base');
        }

        return $user->can('manage-base') || $user->can('manage-app-'.$this->appIdentifier);
    }

    public function canEdit(BackgroundActivityDefinition $definition): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        if ($definition->appIdentifier === 'base') {
            return $user->can('manage-base');
        }

        return $user->can('manage-base') || $user->can('manage-app-'.$definition->appIdentifier);
    }

    /**
     * @return list<BackgroundActivityDefinition>
     */
    private function definitions(): array
    {
        return $this->scopedDefinitions()
            ->filter(fn (BackgroundActivityDefinition $definition): bool => $definition->kind->value === $this->kindFilter)
            ->filter(fn (BackgroundActivityDefinition $definition): bool => $this->matchesSearch($definition))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, BackgroundActivityDefinition>
     */
    private function scopedDefinitions(): Collection
    {
        return app(BackgroundActivityCatalog::class)
            ->all()
            ->when(
                $this->appIdentifier !== null,
                fn ($items) => $items->where('appIdentifier', $this->appIdentifier),
            )
            ->values();
    }

    /**
     * @return array{scheduled: int, queued: int, mail: int, notification: int}
     */
    private function kindCounts(): array
    {
        $counts = [
            'scheduled' => 0,
            'queued' => 0,
            'mail' => 0,
            'notification' => 0,
        ];

        foreach ($this->scopedDefinitions() as $definition) {
            if (! $this->matchesSearch($definition)) {
                continue;
            }

            $counts[$definition->kind->value]++;
        }

        return $counts;
    }

    private function matchesText(string $haystack): bool
    {
        $term = mb_strtolower(trim($this->search));

        if ($term === '') {
            return true;
        }

        return str_contains(mb_strtolower($haystack), $term);
    }

    private function matchesSearch(BackgroundActivityDefinition $definition): bool
    {
        $haystack = implode(' ', array_filter([
            $definition->label,
            $definition->description,
            $definition->key,
            $definition->appName,
            $definition->appIdentifier,
            $definition->command,
            $definition->job,
            $definition->invokable,
            $definition->mailable,
        ]));

        return $this->matchesText($haystack);
    }

    /**
     * @return array<string, array{name: string, rows: list<array<string, mixed>>}>
     */
    private function groups(): array
    {
        $definitions = $this->definitions();
        $latest = $this->latestRuns(array_map(fn (BackgroundActivityDefinition $definition): string => $definition->key, $definitions));
        $groups = [];

        foreach ($definitions as $definition) {
            $groups[$definition->appIdentifier]['name'] = $definition->appName;
            $groups[$definition->appIdentifier]['rows'][] = $this->present($definition, $latest);
        }

        uasort($groups, fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        return $groups;
    }

    /**
     * @param  array<string, array<string, mixed>>  $latest
     * @return array<string, mixed>
     */
    private function present(BackgroundActivityDefinition $definition, array $latest): array
    {
        $store = app(BackgroundActivityConfigStore::class);
        $frequency = $definition->kind === BackgroundActivityKind::Scheduled
            ? $store->frequency($definition)
            : null;
        $cron = $frequency?->toCron();
        $channels = [];

        foreach ($definition->availableChannels ?? [] as $channel) {
            $run = $latest[$definition->key.'|'.$channel] ?? null;
            $channels[] = [
                'key' => $channel,
                'status' => $run['status'] ?? null,
                'at' => $run['finished_at'] ?? null,
                'exception' => $run['exception'] ?? null,
                'count' => (int) ($run['count'] ?? 0),
                'success_count' => (int) ($run['success_count'] ?? 0),
                'failed_count' => (int) ($run['failed_count'] ?? 0),
            ];
        }

        $run = $latest[$definition->key.'|'] ?? null;

        return [
            'definition' => $definition,
            'wireKey' => self::toWireKey($definition->key),
            'enabled' => $definition->kind === BackgroundActivityKind::Scheduled ? $store->isEnabled($definition) : null,
            'frequencyLabel' => $frequency?->label(),
            'cron' => $cron,
            'nextRun' => $cron !== null ? $this->nextRunLabel($cron) : null,
            'overdue' => $cron !== null && ($definition->kind !== BackgroundActivityKind::Scheduled || $store->isEnabled($definition))
                ? $this->isOverdue($cron, $run['finished_carbon'] ?? null, $run['status'] ?? null)
                : false,
            'lastStatus' => $run['status'] ?? null,
            'lastAt' => $run['finished_at'] ?? null,
            'lastException' => $run['exception'] ?? null,
            'lastOutput' => $run['output'] ?? null,
            'durationMs' => $run['duration_ms'] ?? null,
            'channels' => $channels,
            'editable' => $definition->kind === BackgroundActivityKind::Scheduled && $this->canEdit($definition),
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, array<string, mixed>>
     */
    private function latestRuns(array $keys): array
    {
        if ($keys === [] || ! $this->runsTableReady()) {
            return [];
        }

        $grouped = [];

        $runsByKey = [];

        BackgroundActivityRun::query()
            ->whereIn('activity_key', $keys)
            ->orderByDesc('id')
            ->get()
            ->each(function (BackgroundActivityRun $run) use (&$runsByKey): void {
                $runsByKey[$run->activity_key.'|'.($run->channel ?? '')][] = $run;
            });

        foreach ($runsByKey as $mapKey => $runs) {
            $grouped[$mapKey] = $this->summarizeRuns($runs);
        }

        return $grouped;
    }

    /**
     * @param  list<BackgroundActivityRun>  $runs
     * @return array<string, mixed>
     */
    private function summarizeRuns(array $runs): array
    {
        $latest = $runs[0];
        $notificationBurst = $latest->trigger === BackgroundActivityTrigger::Notification;
        $success = 0;
        $failed = 0;
        $previous = $latest->finished_at;

        foreach ($runs as $index => $run) {
            if ($index > 0 && ! $notificationBurst) {
                break;
            }

            if ($index > 0 && $notificationBurst && $previous instanceof \DateTimeInterface && $run->finished_at instanceof \DateTimeInterface) {
                if (abs($previous->getTimestamp() - $run->finished_at->getTimestamp()) > 180) {
                    break;
                }
            }

            $deliveries = max(1, (int) ($run->deliveries ?? 1));

            if ($run->status === BackgroundActivityRunStatus::Failed) {
                $failed += $deliveries;
            } else {
                $success += $deliveries;
            }

            $previous = $run->finished_at;
        }

        return [
            'status' => $latest->status?->value,
            'finished_at' => $latest->finished_at?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
            'exception' => $latest->exception,
            'output' => is_array($latest->meta) ? ($latest->meta['output'] ?? null) : null,
            'duration_ms' => $latest->duration_ms,
            'finished_carbon' => $latest->finished_at,
            'count' => $success + $failed,
            'success_count' => $success,
            'failed_count' => $failed,
        ];
    }

    /**
     * @return list<array{key: string, status: ?string, at: ?string, exception: ?string}>
     */
    private function uncataloguedMails(): array
    {
        if ($this->appIdentifier !== null || ! $this->runsTableReady()) {
            return [];
        }

        $rows = [];

        BackgroundActivityRun::query()
            ->where('activity_key', 'like', 'mail.uncatalogued.%')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->each(function (BackgroundActivityRun $run) use (&$rows): void {
                if (isset($rows[$run->activity_key])) {
                    return;
                }

                $rows[$run->activity_key] = [
                    'key' => $run->activity_key,
                    'status' => $run->status?->value,
                    'at' => $run->finished_at?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
                    'exception' => $run->exception,
                ];
            });

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->matchesText($row['key'].' '.($row['exception'] ?? '')),
        ));
    }

    /**
     * @return list<array{key: string, channel: string, at: ?string, exception: ?string}>
     */
    private function failedNotifications(): array
    {
        if (! $this->runsTableReady()) {
            return [];
        }

        return BackgroundActivityRun::query()
            ->where('status', BackgroundActivityRunStatus::Failed)
            ->where('trigger', 'notification')
            ->when(
                $this->appIdentifier !== null,
                fn ($query) => $query->where('activity_key', 'like', $this->appIdentifier.'.%'),
            )
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (BackgroundActivityRun $run): array => [
                'key' => $run->activity_key,
                'channel' => (string) $run->channel,
                'at' => $run->finished_at?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
                'exception' => $run->exception,
            ])
            ->filter(fn (array $row): bool => $this->matchesText($row['key'].' '.$row['channel'].' '.($row['exception'] ?? '')))
            ->values()
            ->all();
    }

    private function nextRunLabel(string $cron): ?string
    {
        try {
            $next = CronExpression::factory($cron)->getNextRunDate('now', 0, false, config('app.timezone'));

            return $next->format('d.m.Y H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    private function isOverdue(string $cron, mixed $finishedAt, ?string $status): bool
    {
        if ($status === BackgroundActivityRunStatus::Failed->value) {
            return true;
        }

        if (! $finishedAt instanceof \DateTimeInterface) {
            return false;
        }

        try {
            $expression = CronExpression::factory($cron);
            $previous = $expression->getPreviousRunDate();
            $before = $expression->getPreviousRunDate($previous);
            $interval = max(60, $previous->getTimestamp() - $before->getTimestamp());

            return $finishedAt->getTimestamp() < (time() - (2 * $interval));
        } catch (\Throwable) {
            return false;
        }
    }

    private function runsTableReady(): bool
    {
        try {
            return Schema::hasTable('intranet_background_activity_runs');
        } catch (\Throwable) {
            return false;
        }
    }
}
