<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Contracts\AiConfigResolverInterface;
use Hwkdo\IntranetAppBase\Contracts\AppAiSettingsSourceInterface;
use Hwkdo\IntranetAppBase\Contracts\HasAiSettings;
use Hwkdo\IntranetAppBase\Contracts\IntranetBaseAiConfigSourceInterface;
use Hwkdo\IntranetAppBase\Data\ResolvedAiConfig;
use Hwkdo\IntranetAppBase\Enums\AiCapability;
use Hwkdo\IntranetAppBase\Enums\AiConfigSource;
use Hwkdo\IntranetAppBase\Enums\AiProvider;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

class AiConfigResolver implements AiConfigResolverInterface
{
    public function __construct(
        private readonly IntranetBaseAiConfigSourceInterface $baseConfig,
        private readonly AppAiSettingsSourceInterface $appSettingsSource,
        private readonly ConfigRepository $config,
    ) {}

    public function resolve(string $appIdentifier, AiCapability $capability): ResolvedAiConfig
    {
        return $this->resolveWithContext(
            $appIdentifier,
            $capability,
            $this->appSettingsSource->forApp($appIdentifier),
        );
    }

    public function resolveWithContext(
        string $appIdentifier,
        AiCapability $capability,
        ?HasAiSettings $appSettings = null,
    ): ResolvedAiConfig {
        $providerOverride = $this->providerOverrideFor($capability, $appSettings);
        $modelOverride = $this->modelOverrideFor($capability, $appSettings);

        if ($providerOverride !== null) {
            return new ResolvedAiConfig(
                provider: $providerOverride,
                model: $modelOverride ?? $this->defaultModelFor($providerOverride, $capability),
                source: AiConfigSource::AppOverride,
                capability: $capability,
            );
        }

        $baseProvider = $this->baseProviderFor($capability);
        $baseModel = $this->baseModelFor($capability);

        if ($baseProvider !== null) {
            return new ResolvedAiConfig(
                provider: $baseProvider,
                model: $modelOverride ?? $baseModel ?? $this->defaultModelFor($baseProvider, $capability),
                source: AiConfigSource::Base,
                capability: $capability,
            );
        }

        $configProvider = $this->configDefaultProvider($capability);

        return new ResolvedAiConfig(
            provider: $configProvider,
            model: $modelOverride ?? $baseModel ?? $this->defaultModelFor($configProvider, $capability),
            source: AiConfigSource::ConfigDefault,
            capability: $capability,
        );
    }

    private function providerOverrideFor(AiCapability $capability, ?HasAiSettings $appSettings): ?AiProvider
    {
        if ($appSettings === null) {
            return null;
        }

        return match ($capability) {
            AiCapability::Image => $appSettings->imageProviderOverride(),
            default => $appSettings->textProviderOverride(),
        };
    }

    private function modelOverrideFor(AiCapability $capability, ?HasAiSettings $appSettings): ?string
    {
        if ($appSettings === null) {
            return null;
        }

        return match ($capability) {
            AiCapability::Image => $appSettings->imageModelOverride(),
            default => $appSettings->textModelOverride(),
        };
    }

    private function baseProviderFor(AiCapability $capability): ?AiProvider
    {
        return match ($capability) {
            AiCapability::Image => $this->baseConfig->imageProvider(),
            default => $this->baseConfig->textProvider(),
        };
    }

    private function baseModelFor(AiCapability $capability): ?string
    {
        return match ($capability) {
            AiCapability::Image => $this->baseConfig->imageModel(),
            default => $this->baseConfig->textModel(),
        };
    }

    private function configDefaultProvider(AiCapability $capability): AiProvider
    {
        $key = $this->config->get('ai.'.$capability->configDefaultKey(), 'openai');

        return AiProvider::tryFrom((string) $key) ?? AiProvider::OpenAi;
    }

    private function defaultModelFor(AiProvider $provider, AiCapability $capability): ?string
    {
        $section = $capability->modelConfigSection();
        $model = $this->config->get("ai.providers.{$provider->value}.models.{$section}.default");

        if (is_string($model) && trim($model) !== '') {
            return trim($model);
        }

        return null;
    }
}
