<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Contracts;

use Hwkdo\IntranetAppBase\Enums\AiProvider;

interface IntranetBaseAiConfigSourceInterface
{
    public function textProvider(): AiProvider;

    public function textModel(): ?string;

    public function imageProvider(): AiProvider;

    public function imageModel(): ?string;
}
