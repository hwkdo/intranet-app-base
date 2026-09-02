<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Data\TourDefinition;
use Hwkdo\IntranetAppBase\Enums\TourStatus;
use Hwkdo\IntranetAppBase\Models\IntranetUserTourCompletion;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TourProgressStore
{
    public const DEFAULT_REMIND_HOURS = 24;

    public function find(Authenticatable $user, string $tourKey): ?IntranetUserTourCompletion
    {
        return IntranetUserTourCompletion::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('tour_key', $tourKey)
            ->first();
    }

    public function effectiveStatus(Authenticatable $user, string $tourKey): TourStatus
    {
        $record = $this->find($user, $tourKey);

        return $record?->status ?? TourStatus::Pending;
    }

    public function isCompleted(Authenticatable $user, string $tourKey): bool
    {
        return $this->effectiveStatus($user, $tourKey) === TourStatus::Completed;
    }

    /**
     * Soft-Mandatory: Tour muss einmal completed sein.
     * Version-Bumps erzwingen keine erneute Pflicht.
     */
    public function requiresMandatoryCompletion(Authenticatable $user, TourDefinition $definition): bool
    {
        return $definition->mandatory && ! $this->isCompleted($user, $definition->key);
    }

    public function markCompleted(Authenticatable $user, TourDefinition $definition): IntranetUserTourCompletion
    {
        return $this->upsert($user, $definition->key, [
            'status' => TourStatus::Completed,
            'version' => $definition->version,
            'remind_after' => null,
            'completed_at' => now(),
            'dismissed_at' => null,
        ]);
    }

    public function markDismissed(Authenticatable $user, TourDefinition $definition): IntranetUserTourCompletion
    {
        return $this->upsert($user, $definition->key, [
            'status' => TourStatus::Dismissed,
            'version' => $definition->version,
            'remind_after' => null,
            'completed_at' => null,
            'dismissed_at' => now(),
        ]);
    }

    public function markRemindLater(
        Authenticatable $user,
        TourDefinition $definition,
        int $hours = self::DEFAULT_REMIND_HOURS,
    ): IntranetUserTourCompletion {
        return $this->upsert($user, $definition->key, [
            'status' => TourStatus::RemindLater,
            'version' => $definition->version,
            'remind_after' => Carbon::now()->addHours($hours),
            'completed_at' => null,
            'dismissed_at' => null,
        ]);
    }

    public function reset(Authenticatable $user, string $tourKey): void
    {
        IntranetUserTourCompletion::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('tour_key', $tourKey)
            ->delete();
    }

    /**
     * @param  Collection<string, TourDefinition>  $definitions
     * @return Collection<string, TourDefinition>
     */
    public function nudgeableFor(Authenticatable $user, Collection $definitions): Collection
    {
        $keys = $definitions->keys()->all();

        if ($keys === []) {
            return collect();
        }

        /** @var Collection<string, IntranetUserTourCompletion> $records */
        $records = IntranetUserTourCompletion::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereIn('tour_key', $keys)
            ->get()
            ->keyBy('tour_key');

        return $definitions->filter(function (TourDefinition $definition) use ($records): bool {
            $record = $records->get($definition->key);

            if ($record === null) {
                return true;
            }

            if ($record->version < $definition->version) {
                return true;
            }

            return match ($record->status) {
                TourStatus::Completed, TourStatus::Dismissed => false,
                TourStatus::RemindLater => $record->remind_after === null || $record->remind_after->isPast(),
                TourStatus::Pending => true,
            };
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsert(Authenticatable $user, string $tourKey, array $attributes): IntranetUserTourCompletion
    {
        /** @var IntranetUserTourCompletion $record */
        $record = IntranetUserTourCompletion::query()->updateOrCreate(
            [
                'user_id' => $user->getAuthIdentifier(),
                'tour_key' => $tourKey,
            ],
            $attributes,
        );

        return $record;
    }
}
