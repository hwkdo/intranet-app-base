<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Data\SetupDefinition;
use Hwkdo\IntranetAppBase\Enums\SetupStatus;
use Hwkdo\IntranetAppBase\Models\IntranetUserSetupCompletion;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SetupProgressStore
{
    public const DEFAULT_REMIND_HOURS = 24;

    public function find(Authenticatable $user, string $setupKey): ?IntranetUserSetupCompletion
    {
        return IntranetUserSetupCompletion::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('setup_key', $setupKey)
            ->first();
    }

    public function effectiveStatus(Authenticatable $user, string $setupKey): SetupStatus
    {
        $record = $this->find($user, $setupKey);

        return $record?->status ?? SetupStatus::Pending;
    }

    public function markCompleted(Authenticatable $user, SetupDefinition $definition): IntranetUserSetupCompletion
    {
        return $this->upsert($user, $definition->key, [
            'status' => SetupStatus::Completed,
            'version' => $definition->version,
            'remind_after' => null,
            'completed_at' => now(),
            'dismissed_at' => null,
        ]);
    }

    public function markDismissed(Authenticatable $user, SetupDefinition $definition): IntranetUserSetupCompletion
    {
        return $this->upsert($user, $definition->key, [
            'status' => SetupStatus::Dismissed,
            'version' => $definition->version,
            'remind_after' => null,
            'completed_at' => null,
            'dismissed_at' => now(),
        ]);
    }

    public function markRemindLater(
        Authenticatable $user,
        SetupDefinition $definition,
        int $hours = self::DEFAULT_REMIND_HOURS,
    ): IntranetUserSetupCompletion {
        return $this->upsert($user, $definition->key, [
            'status' => SetupStatus::RemindLater,
            'version' => $definition->version,
            'remind_after' => Carbon::now()->addHours($hours),
            'completed_at' => null,
            'dismissed_at' => null,
        ]);
    }

    public function reset(Authenticatable $user, string $setupKey): void
    {
        IntranetUserSetupCompletion::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('setup_key', $setupKey)
            ->delete();
    }

    /**
     * @param  Collection<string, SetupDefinition>  $definitions
     * @return Collection<string, SetupDefinition>
     */
    public function nudgeableFor(Authenticatable $user, Collection $definitions): Collection
    {
        $keys = $definitions->keys()->all();

        if ($keys === []) {
            return collect();
        }

        /** @var Collection<string, IntranetUserSetupCompletion> $records */
        $records = IntranetUserSetupCompletion::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereIn('setup_key', $keys)
            ->get()
            ->keyBy('setup_key');

        return $definitions->filter(function (SetupDefinition $definition) use ($records): bool {
            $record = $records->get($definition->key);

            if ($record === null) {
                return true;
            }

            return match ($record->status) {
                SetupStatus::Completed, SetupStatus::Dismissed => false,
                SetupStatus::RemindLater => $record->remind_after === null || $record->remind_after->isPast(),
                SetupStatus::Pending => true,
            };
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsert(Authenticatable $user, string $setupKey, array $attributes): IntranetUserSetupCompletion
    {
        /** @var IntranetUserSetupCompletion $record */
        $record = IntranetUserSetupCompletion::query()->updateOrCreate(
            [
                'user_id' => $user->getAuthIdentifier(),
                'setup_key' => $setupKey,
            ],
            $attributes,
        );

        return $record;
    }
}
