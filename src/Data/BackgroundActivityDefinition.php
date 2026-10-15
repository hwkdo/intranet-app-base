<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

use Hwkdo\IntranetAppBase\Enums\BackgroundActivityCadence;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityKind;

final class BackgroundActivityDefinition
{
    /**
     * @param  list<BackgroundActivityCadence>  $allowedCadences
     * @param  list<string>|null  $environments
     * @param  list<BackgroundActivityParameter>  $parameters
     * @param  list<string>|null  $availableChannels
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $appIdentifier,
        public readonly string $appName,
        public readonly BackgroundActivityKind $kind,
        public readonly ?string $description = null,
        public readonly ?string $command = null,
        public readonly ?string $job = null,
        public readonly ?string $invokable = null,
        public readonly ?string $mailable = null,
        public readonly ?BackgroundActivityFrequency $defaultFrequency = null,
        public readonly array $allowedCadences = [],
        public readonly ?int $minEvery = null,
        public readonly ?int $maxEvery = null,
        public readonly bool $allowsOffset = false,
        public readonly bool $defaultEnabled = true,
        public readonly int $withoutOverlappingMinutes = 0,
        public readonly bool $runInBackground = false,
        public readonly ?array $environments = null,
        public readonly array $parameters = [],
        public readonly ?array $availableChannels = null,
        public readonly ?string $appendOutputTo = null,
        public readonly int $sort = 100,
    ) {}

    public function commandName(): ?string
    {
        if ($this->command === null || $this->command === '') {
            return null;
        }

        return explode(' ', trim($this->command))[0];
    }

    /**
     * @return list<BackgroundActivityCadence>
     */
    public function resolvedAllowedCadences(): array
    {
        if ($this->allowedCadences !== []) {
            return $this->allowedCadences;
        }

        if ($this->defaultFrequency !== null) {
            return [$this->defaultFrequency->cadence];
        }

        return BackgroundActivityCadence::cases();
    }

    /**
     * @param  list<BackgroundActivityCadence>  $allowedCadences
     * @param  list<string>|null  $environments
     * @param  list<BackgroundActivityParameter>  $parameters
     */
    public static function scheduled(
        string $key,
        string $label,
        string $appIdentifier,
        string $appName,
        string $command,
        BackgroundActivityFrequency $frequency,
        ?string $description = null,
        array $allowedCadences = [],
        ?int $minEvery = null,
        ?int $maxEvery = null,
        bool $allowsOffset = false,
        bool $defaultEnabled = true,
        int $withoutOverlappingMinutes = 0,
        bool $runInBackground = false,
        ?array $environments = null,
        array $parameters = [],
        ?string $appendOutputTo = null,
        int $sort = 100,
    ): self {
        return new self(
            key: $key,
            label: $label,
            appIdentifier: $appIdentifier,
            appName: $appName,
            kind: BackgroundActivityKind::Scheduled,
            description: $description,
            command: $command,
            defaultFrequency: $frequency,
            allowedCadences: $allowedCadences,
            minEvery: $minEvery,
            maxEvery: $maxEvery,
            allowsOffset: $allowsOffset,
            defaultEnabled: $defaultEnabled,
            withoutOverlappingMinutes: $withoutOverlappingMinutes,
            runInBackground: $runInBackground,
            environments: $environments,
            parameters: $parameters,
            appendOutputTo: $appendOutputTo,
            sort: $sort,
        );
    }

    public static function scheduledJob(
        string $key,
        string $label,
        string $appIdentifier,
        string $appName,
        string $job,
        BackgroundActivityFrequency $frequency,
        ?string $description = null,
        array $allowedCadences = [],
        ?int $minEvery = null,
        ?int $maxEvery = null,
        bool $defaultEnabled = true,
        int $sort = 100,
    ): self {
        return new self(
            key: $key,
            label: $label,
            appIdentifier: $appIdentifier,
            appName: $appName,
            kind: BackgroundActivityKind::Scheduled,
            description: $description,
            job: $job,
            defaultFrequency: $frequency,
            allowedCadences: $allowedCadences,
            minEvery: $minEvery,
            maxEvery: $maxEvery,
            defaultEnabled: $defaultEnabled,
            sort: $sort,
        );
    }

    public static function queued(
        string $key,
        string $label,
        string $appIdentifier,
        string $appName,
        string $job,
        ?string $description = null,
        int $sort = 100,
    ): self {
        return new self(
            key: $key,
            label: $label,
            appIdentifier: $appIdentifier,
            appName: $appName,
            kind: BackgroundActivityKind::Queued,
            description: $description,
            job: $job,
            sort: $sort,
        );
    }

    public static function mail(
        string $key,
        string $label,
        string $appIdentifier,
        string $appName,
        string $mailable,
        ?string $description = null,
        int $sort = 100,
    ): self {
        return new self(
            key: $key,
            label: $label,
            appIdentifier: $appIdentifier,
            appName: $appName,
            kind: BackgroundActivityKind::Mail,
            description: $description,
            mailable: $mailable,
            sort: $sort,
        );
    }
}
