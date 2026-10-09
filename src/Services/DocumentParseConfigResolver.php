<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Contracts\AppDocumentParseSettingsSourceInterface;
use Hwkdo\IntranetAppBase\Contracts\DocumentParseConfigResolverInterface;
use Hwkdo\IntranetAppBase\Contracts\IntranetBaseAiConfigSourceInterface;
use Hwkdo\IntranetAppBase\Data\ResolvedDocumentParseConfig;
use Hwkdo\IntranetAppBase\Enums\AiConfigSource;

class DocumentParseConfigResolver implements DocumentParseConfigResolverInterface
{
    public function __construct(
        private readonly IntranetBaseAiConfigSourceInterface $baseConfig,
        private readonly AppDocumentParseSettingsSourceInterface $appSettingsSource,
    ) {}

    public function resolve(string $appIdentifier): ResolvedDocumentParseConfig
    {
        $override = $this->appSettingsSource->forApp($appIdentifier);
        $engine = $override?->documentParseEngine();

        if ($engine !== null) {
            return new ResolvedDocumentParseConfig(
                engine: $engine,
                llamaParseTier: $override->documentParseTier() ?? $this->baseConfig->documentParseTier(),
                source: AiConfigSource::AppOverride,
            );
        }

        return new ResolvedDocumentParseConfig(
            engine: $this->baseConfig->documentParseEngine(),
            llamaParseTier: $this->baseConfig->documentParseTier(),
            source: AiConfigSource::Base,
        );
    }
}
