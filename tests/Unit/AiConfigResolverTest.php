<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBase\Contracts\AppAiSettingsSourceInterface;
use Hwkdo\IntranetAppBase\Contracts\HasAiSettings;
use Hwkdo\IntranetAppBase\Contracts\IntranetBaseAiConfigSourceInterface;
use Hwkdo\IntranetAppBase\Data\BaseAppSettings;
use Hwkdo\IntranetAppBase\Enums\AiCapability;
use Hwkdo\IntranetAppBase\Enums\AiConfigSource;
use Hwkdo\IntranetAppBase\Enums\AiProvider;
use Hwkdo\IntranetAppBase\Enums\DocumentParseEngine;
use Hwkdo\IntranetAppBase\Services\AiConfigResolver;
use Hwkdo\IntranetAppBase\Traits\HasAiSettingsFields;
use Illuminate\Config\Repository as ConfigRepository;

class AiConfigResolverTestAppSettings extends BaseAppSettings implements HasAiSettings
{
    use HasAiSettingsFields;

    public function __construct(
        ?AiProvider $aiTextProviderOverride = null,
        ?string $aiTextModelOverride = null,
        ?AiProvider $aiImageProviderOverride = null,
        ?string $aiImageModelOverride = null,
    ) {
        $this->aiTextProviderOverride = $aiTextProviderOverride;
        $this->aiTextModelOverride = $aiTextModelOverride;
        $this->aiImageProviderOverride = $aiImageProviderOverride;
        $this->aiImageModelOverride = $aiImageModelOverride;
    }
}

class AiConfigResolverTestBaseSource implements IntranetBaseAiConfigSourceInterface
{
    public function __construct(
        private readonly AiProvider $textProvider = AiProvider::Langdock,
        private readonly ?string $textModel = 'gpt-4o',
        private readonly AiProvider $imageProvider = AiProvider::OpenAi,
        private readonly ?string $imageModel = 'gpt-image-1.5',
        private readonly ?string $imageEditModel = 'gpt-image-1.5-edit',
        private readonly AiProvider $classificationProvider = AiProvider::Bifrost,
        private readonly ?string $classificationModel = 'openrouter/~typesafe/jev-latest',
    ) {}

    public function textProvider(): AiProvider
    {
        return $this->textProvider;
    }

    public function textModel(): ?string
    {
        return $this->textModel;
    }

    public function imageProvider(): AiProvider
    {
        return $this->imageProvider;
    }

    public function imageModel(): ?string
    {
        return $this->imageModel;
    }

    public function imageEditModel(): ?string
    {
        return $this->imageEditModel;
    }

    public function classificationProvider(): AiProvider
    {
        return $this->classificationProvider;
    }

    public function classificationModel(): ?string
    {
        return $this->classificationModel;
    }

    public function documentParseEngine(): DocumentParseEngine
    {
        return DocumentParseEngine::LlamaParse;
    }

    public function documentParseTier(): string
    {
        return 'cost_effective';
    }
}

class AiConfigResolverTestAppSource implements AppAiSettingsSourceInterface
{
    public function __construct(
        private readonly ?HasAiSettings $settings = null,
    ) {}

    public function forApp(string $appIdentifier): ?HasAiSettings
    {
        return $this->settings;
    }
}

function makeAiConfigResolver(
    ?AiConfigResolverTestBaseSource $base = null,
    ?HasAiSettings $appSettings = null,
    array $aiConfig = [],
): AiConfigResolver {
    $config = new ConfigRepository([
        'ai' => array_merge([
            'default' => 'openai',
            'default_for_images' => 'openai',
            'providers' => [
                'openai' => [
                    'models' => [
                        'text' => ['default' => 'gpt-4o'],
                        'image' => ['default' => 'gpt-image-1.5'],
                    ],
                ],
                'langdock' => [
                    'models' => [
                        'text' => ['default' => 'gpt-5.4'],
                    ],
                ],
            ],
        ], $aiConfig),
    ]);

    return new AiConfigResolver(
        $base ?? new AiConfigResolverTestBaseSource,
        new AiConfigResolverTestAppSource($appSettings),
        $config,
    );
}

it('resolves text provider from base settings when no app override exists', function () {
    $resolver = makeAiConfigResolver();

    $resolved = $resolver->resolve('tickets', AiCapability::Text);

    expect($resolved->provider)->toBe(AiProvider::Langdock)
        ->and($resolved->model)->toBe('gpt-4o')
        ->and($resolved->source)->toBe(AiConfigSource::Base);
});

it('resolves classification provider from base settings', function () {
    $resolver = makeAiConfigResolver();

    $resolved = $resolver->resolve('assets', AiCapability::Classification);

    expect($resolved->provider)->toBe(AiProvider::Bifrost)
        ->and($resolved->model)->toBe('openrouter/~typesafe/jev-latest')
        ->and($resolved->source)->toBe(AiConfigSource::Base);
});

it('prefers a classification app override', function () {
    $appSettings = new AiConfigResolverTestAppSettings;
    $appSettings->aiClassificationProviderOverride = AiProvider::OpenAi;
    $appSettings->aiClassificationModelOverride = 'gpt-6-luna';

    $resolver = makeAiConfigResolver(appSettings: $appSettings);

    $resolved = $resolver->resolve('assets', AiCapability::Classification);

    expect($resolved->provider)->toBe(AiProvider::OpenAi)
        ->and($resolved->model)->toBe('gpt-6-luna')
        ->and($resolved->source)->toBe(AiConfigSource::AppOverride);
});

it('resolves the base image edit model', function () {
    $resolver = makeAiConfigResolver();

    expect($resolver->resolveImageEditModel('tippspiel'))->toBe('gpt-image-1.5-edit');
});

it('prefers an image edit app override', function () {
    $appSettings = new AiConfigResolverTestAppSettings;
    $appSettings->aiImageEditModelOverride = 'custom-edit';

    $resolver = makeAiConfigResolver(appSettings: $appSettings);

    expect($resolver->resolveImageEditModel('assets'))->toBe('custom-edit');
});

it('uses the provider edit default when the app overrides only the image provider', function () {
    $appSettings = new AiConfigResolverTestAppSettings(
        aiImageProviderOverride: AiProvider::Bifrost,
    );

    $resolver = makeAiConfigResolver(appSettings: $appSettings, aiConfig: [
        'providers' => [
            'bifrost' => [
                'models' => [
                    'image' => ['edit' => 'qwen-edit'],
                ],
            ],
        ],
    ]);

    expect($resolver->resolveImageEditModel('assets'))->toBe('qwen-edit');
});

it('resolves image provider from base settings', function () {
    $resolver = makeAiConfigResolver();

    $resolved = $resolver->resolve('tippspiel', AiCapability::Image);

    expect($resolved->provider)->toBe(AiProvider::OpenAi)
        ->and($resolved->model)->toBe('gpt-image-1.5')
        ->and($resolved->source)->toBe(AiConfigSource::Base);
});

it('prefers app override over base settings', function () {
    $appSettings = new AiConfigResolverTestAppSettings(
        aiTextProviderOverride: AiProvider::OpenWebUi,
        aiTextModelOverride: 'gpt-oss:20b',
    );

    $resolver = makeAiConfigResolver(appSettings: $appSettings);

    $resolved = $resolver->resolve('tickets', AiCapability::Text);

    expect($resolved->provider)->toBe(AiProvider::OpenWebUi)
        ->and($resolved->model)->toBe('gpt-oss:20b')
        ->and($resolved->source)->toBe(AiConfigSource::AppOverride);
});

it('falls back to config default when base source is empty', function () {
    $base = new AiConfigResolverTestBaseSource(
        textProvider: AiProvider::OpenAi,
        textModel: null,
        imageProvider: AiProvider::OpenAi,
        imageModel: null,
    );

    $resolver = makeAiConfigResolver($base);

    $resolved = $resolver->resolve('news', AiCapability::Text);

    expect($resolved->provider)->toBe(AiProvider::OpenAi)
        ->and($resolved->model)->toBe('gpt-4o')
        ->and($resolved->source)->toBe(AiConfigSource::Base);
});

it('uses provider model default from config when model is not set', function () {
    $base = new AiConfigResolverTestBaseSource(
        textProvider: AiProvider::Langdock,
        textModel: null,
        imageProvider: AiProvider::OpenAi,
        imageModel: null,
    );

    $resolver = makeAiConfigResolver($base);

    $resolved = $resolver->resolve('bewerbungen', AiCapability::Agent);

    expect($resolved->provider)->toBe(AiProvider::Langdock)
        ->and($resolved->model)->toBe('gpt-5.4');
});

it('treats blank app model override as null', function () {
    $appSettings = new AiConfigResolverTestAppSettings(
        aiTextProviderOverride: AiProvider::OpenAi,
        aiTextModelOverride: '   ',
    );

    $resolver = makeAiConfigResolver(appSettings: $appSettings);

    $resolved = $resolver->resolve('tickets', AiCapability::Text);

    expect($resolved->model)->toBe('gpt-4o');
});
