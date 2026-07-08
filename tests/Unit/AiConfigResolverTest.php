<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBase\Contracts\HasAiSettings;
use Hwkdo\IntranetAppBase\Data\BaseAppSettings;
use Hwkdo\IntranetAppBase\Enums\AiCapability;
use Hwkdo\IntranetAppBase\Enums\AiConfigSource;
use Hwkdo\IntranetAppBase\Enums\AiProvider;
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

class AiConfigResolverTestBaseSource implements \Hwkdo\IntranetAppBase\Contracts\IntranetBaseAiConfigSourceInterface
{
    public function __construct(
        private readonly AiProvider $textProvider = AiProvider::Langdock,
        private readonly ?string $textModel = 'gpt-4o',
        private readonly AiProvider $imageProvider = AiProvider::OpenAi,
        private readonly ?string $imageModel = 'gpt-image-1.5',
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
}

class AiConfigResolverTestAppSource implements \Hwkdo\IntranetAppBase\Contracts\AppAiSettingsSourceInterface
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
