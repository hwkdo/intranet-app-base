<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Enums\BackgroundActivityRunStatus;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityTrigger;
use Hwkdo\IntranetAppBase\Models\BackgroundActivityRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackgroundActivityRunStore
{
    private const KEEP = 30;

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function record(
        string $activityKey,
        BackgroundActivityRunStatus $status,
        BackgroundActivityTrigger $trigger,
        ?string $channel = null,
        ?string $exception = null,
        ?int $durationMs = null,
        ?array $meta = null,
        ?\DateTimeInterface $startedAt = null,
    ): void {
        if (! $this->tableReady()) {
            return;
        }

        if ($trigger === BackgroundActivityTrigger::Notification && $channel !== null) {
            $this->recordNotificationDelivery($activityKey, $status, $channel, $exception);

            return;
        }

        $finished = now();

        BackgroundActivityRun::query()->create([
            'activity_key' => $activityKey,
            'channel' => $channel,
            'status' => $status,
            'trigger' => $trigger,
            'started_at' => $startedAt ?? $finished,
            'finished_at' => $finished,
            'duration_ms' => $durationMs,
            'exception' => $exception !== null ? mb_substr($exception, 0, 2000) : null,
            'meta' => $meta,
        ]);

        $this->prune($activityKey, $channel);
    }

    private function recordNotificationDelivery(
        string $activityKey,
        BackgroundActivityRunStatus $status,
        string $channel,
        ?string $exception,
    ): void {
        $now = now();
        $bucket = $now->copy()->startOfMinute();
        $update = [
            'deliveries' => DB::raw('deliveries + 1'),
            'finished_at' => $now,
            'updated_at' => $now,
        ];

        if ($exception !== null) {
            $update['exception'] = mb_substr($exception, 0, 2000);
        }

        DB::table('intranet_background_activity_runs')->upsert(
            [[
                'activity_key' => $activityKey,
                'channel' => $channel,
                'status' => $status->value,
                'trigger' => BackgroundActivityTrigger::Notification->value,
                'bucket_at' => $bucket,
                'deliveries' => 1,
                'started_at' => $now,
                'finished_at' => $now,
                'exception' => $exception !== null ? mb_substr($exception, 0, 2000) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['activity_key', 'channel', 'status', 'bucket_at'],
            $update,
        );

        $this->prune($activityKey, $channel);
    }

    private function prune(string $activityKey, ?string $channel): void
    {
        $ids = BackgroundActivityRun::query()
            ->where('activity_key', $activityKey)
            ->when(
                $channel === null,
                fn ($query) => $query->whereNull('channel'),
                fn ($query) => $query->where('channel', $channel),
            )
            ->orderByDesc('id')
            ->skip(self::KEEP)
            ->take(100)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        BackgroundActivityRun::query()->whereIn('id', $ids)->delete();
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('intranet_background_activity_runs');
        } catch (\Throwable) {
            return false;
        }
    }
}
