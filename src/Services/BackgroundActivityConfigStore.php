<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Data\BackgroundActivityDefinition;
use Hwkdo\IntranetAppBase\Data\BackgroundActivityFrequency;
use Hwkdo\IntranetAppBase\Data\BackgroundActivityParameter;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityCadence;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityKind;
use Hwkdo\IntranetAppBase\Models\BackgroundActivityConfig;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BackgroundActivityConfigStore
{
    public function isEnabled(BackgroundActivityDefinition $definition): bool
    {
        $row = $this->row($definition->key);

        if ($row === null) {
            return $this->enabledWithoutStoredConfig($definition);
        }

        return $row->enabled;
    }

    public function frequency(BackgroundActivityDefinition $definition): ?BackgroundActivityFrequency
    {
        if ($definition->defaultFrequency === null) {
            return null;
        }

        $row = $this->row($definition->key);

        if ($row === null || $row->cadence === null) {
            return $definition->defaultFrequency;
        }

        return new BackgroundActivityFrequency(
            cadence: $row->cadence,
            every: $row->every,
            offset: $row->offset,
            at: $row->at,
            dayOfWeek: $row->day_of_week,
        );
    }

    public function parameter(string $activityKey, string $name, mixed $default = null): mixed
    {
        $definition = app(BackgroundActivityCatalog::class)->find($activityKey);
        $fallback = $default;

        if ($definition !== null) {
            foreach ($definition->parameters as $parameter) {
                if ($parameter->key === $name) {
                    $fallback = $parameter->default;
                }
            }
        }

        $row = $this->row($activityKey);
        $stored = $row?->parameters ?? [];

        if (is_array($stored) && array_key_exists($name, $stored)) {
            return $stored[$name];
        }

        return $fallback;
    }

    /**
     * @param  array{enabled: bool, cadence?: string, every?: int, offset?: int, at?: ?string, day_of_week?: ?int, parameters?: array<string, mixed>}  $input
     */
    public function save(BackgroundActivityDefinition $definition, array $input): void
    {
        if ($definition->kind !== BackgroundActivityKind::Scheduled || $definition->defaultFrequency === null) {
            throw ValidationException::withMessages([
                'activity' => 'Nur geplante Aktivitäten können konfiguriert werden.',
            ]);
        }

        $frequency = BackgroundActivityFrequency::fromArray([
            'cadence' => $input['cadence'] ?? $definition->defaultFrequency->cadence->value,
            'every' => $input['every'] ?? $definition->defaultFrequency->every,
            'offset' => $input['offset'] ?? 0,
            'at' => $input['at'] ?? $definition->defaultFrequency->at,
            'day_of_week' => $input['day_of_week'] ?? $definition->defaultFrequency->dayOfWeek,
        ]);

        $parameters = $this->validatedParameters($definition, $input['parameters'] ?? []);
        $this->assertFrequency($definition, $frequency);

        if (! $this->tableReady()) {
            throw ValidationException::withMessages([
                'activity' => 'Die Konfigurationstabelle ist noch nicht migriert.',
            ]);
        }

        BackgroundActivityConfig::query()->updateOrCreate(
            ['activity_key' => $definition->key],
            [
                'enabled' => (bool) $input['enabled'],
                'cadence' => $frequency->cadence,
                'every' => $frequency->every,
                'offset' => $this->storedOffset($definition, $frequency),
                'at' => $frequency->at,
                'day_of_week' => $frequency->dayOfWeek,
                'parameters' => $parameters,
            ],
        );
    }

    /**
     * Staging startet neue Zeitpläne aus. Eine gespeicherte Zeile bleibt davon unberührt,
     * Production nutzt defaultEnabled.
     */
    private function enabledWithoutStoredConfig(BackgroundActivityDefinition $definition): bool
    {
        if (app()->environment('staging')) {
            return false;
        }

        return $definition->defaultEnabled;
    }

    private function row(string $key): ?BackgroundActivityConfig
    {
        if (! $this->tableReady()) {
            return null;
        }

        return BackgroundActivityConfig::query()->where('activity_key', $key)->first();
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('intranet_background_activity_configs');
        } catch (\Throwable) {
            return false;
        }
    }

    private function assertFrequency(BackgroundActivityDefinition $definition, BackgroundActivityFrequency $frequency): void
    {
        $allowed = array_map(
            fn ($cadence) => $cadence->value,
            $definition->resolvedAllowedCadences(),
        );

        if (! in_array($frequency->cadence->value, $allowed, true)) {
            throw ValidationException::withMessages([
                'cadence' => 'Diese Kadenz ist für die Aktivität nicht erlaubt.',
            ]);
        }

        if ($frequency->cadence === BackgroundActivityCadence::EveryNMinutes && $frequency->every > 59) {
            throw ValidationException::withMessages([
                'every' => 'Ein Minuten-Intervall ist höchstens 59. Für jede Stunde die Kadenz „Alle N Stunden“ mit Intervall 1 verwenden.',
            ]);
        }

        if ($definition->minEvery !== null && $frequency->every < $definition->minEvery) {
            throw ValidationException::withMessages([
                'every' => 'Das Intervall liegt unter dem Minimum.',
            ]);
        }

        if ($definition->maxEvery !== null && $frequency->every > $definition->maxEvery) {
            throw ValidationException::withMessages([
                'every' => 'Das Intervall liegt über dem Maximum.',
            ]);
        }
    }

    private function storedOffset(BackgroundActivityDefinition $definition, BackgroundActivityFrequency $frequency): int
    {
        if ($frequency->cadence !== BackgroundActivityCadence::EveryNHours && ! $definition->allowsOffset) {
            return 0;
        }

        return max(0, min(59, $frequency->offset));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validatedParameters(BackgroundActivityDefinition $definition, array $input): array
    {
        $stored = [];

        foreach ($definition->parameters as $parameter) {
            $value = $input[$parameter->key] ?? $parameter->default;
            $stored[$parameter->key] = $this->castParameter($parameter, $value);
        }

        return $stored;
    }

    private function castParameter(BackgroundActivityParameter $parameter, mixed $value): mixed
    {
        if ($parameter->type === 'int') {
            $int = (int) $value;

            if ($parameter->min !== null && $int < $parameter->min) {
                throw ValidationException::withMessages([
                    $parameter->key => $parameter->label.' liegt unter dem Minimum.',
                ]);
            }

            if ($parameter->max !== null && $int > $parameter->max) {
                throw ValidationException::withMessages([
                    $parameter->key => $parameter->label.' liegt über dem Maximum.',
                ]);
            }

            return $int;
        }

        if ($parameter->type === 'bool') {
            return (bool) $value;
        }

        return is_string($value) ? $value : (string) $value;
    }
}
