<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Contracts;

use Hwkdo\IntranetAppBase\Data\AiChatResult;
use Hwkdo\IntranetAppBase\Data\AiClassificationQuestion;
use Hwkdo\IntranetAppBase\Data\AiClassificationResult;
use Hwkdo\IntranetAppBase\Data\AiImageOptions;
use Hwkdo\IntranetAppBase\Data\AiImageResult;
use Hwkdo\IntranetAppBase\Data\AiRequestContext;
use Hwkdo\IntranetAppBase\Data\DocumentParseOptions;
use Hwkdo\IntranetAppBase\Enums\DocumentParseEngine;

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

    public function parseDocument(
        string $absolutePath,
        DocumentParseEngine $engine,
        AiRequestContext $context,
        ?DocumentParseOptions $options = null,
    ): string;

    public function parseAppDocument(string $absolutePath, string $appIdentifier, ?int $userId = null): string;

    /**
     * Typisierte Klassifikation mit Wahrscheinlichkeiten (Provider: `ai.default_for_classification`, z. B. TypeSafe).
     *
     * @param  string|array<string, mixed>  $state
     * @param  array<string, AiClassificationQuestion>  $questions
     * @param  array<string, string>  $imagePaths  Name => absoluter Pfad (JPEG/PNG)
     */
    public function classify(
        string|array $state,
        array $questions,
        AiRequestContext $context,
        array $imagePaths = [],
    ): AiClassificationResult;
}
