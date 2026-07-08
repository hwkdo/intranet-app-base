<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Contracts;

use Hwkdo\IntranetAppBase\Data\AiChatResult;
use Hwkdo\IntranetAppBase\Data\AiImageOptions;
use Hwkdo\IntranetAppBase\Data\AiImageResult;
use Hwkdo\IntranetAppBase\Data\AiRequestContext;

interface IntranetAiGatewayInterface
{
    public function text(string $prompt, AiRequestContext $context): string;

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    public function chat(array $messages, AiRequestContext $context, ?array $providerOptions = null): AiChatResult;

    /**
     * @param  array<mixed>  $attachments
     */
    public function agent(object $agent, string $prompt, AiRequestContext $context, array $attachments = []): mixed;

    public function image(string $prompt, AiImageOptions $options, AiRequestContext $context): AiImageResult;

    /**
     * @return array<string, mixed>
     */
    public function chatCompletionWithImageFromPath(
        string $absoluteImagePath,
        string $userText,
        AiRequestContext $context,
        int $requestTimeoutSeconds = 120,
        int $connectTimeoutSeconds = 10,
    ): array;
}
