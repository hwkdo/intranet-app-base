<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Contracts;

use Hwkdo\IntranetAppBase\Enums\AiProvider;
use Hwkdo\IntranetAppBase\Enums\DocumentParseEngine;

interface IntranetBaseAiConfigSourceInterface
{
    public function textProvider(): AiProvider;

    public function textModel(): ?string;

    public function imageProvider(): AiProvider;

    public function imageModel(): ?string;

    public function documentParseEngine(): DocumentParseEngine;

    public function documentParseTier(): string;
}
