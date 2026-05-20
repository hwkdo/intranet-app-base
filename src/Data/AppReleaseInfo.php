<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

use Carbon\CarbonInterface;

readonly class AppReleaseInfo
{
    public function __construct(
        public string $tagName,
        public string $name,
        public string $body,
        public string $htmlUrl,
        public ?CarbonInterface $publishedAt,
    ) {}

    public function normalizedTagName(): string
    {
        $tag = ltrim($this->tagName, 'v');

        return 'v'.$tag;
    }

    public function displayTitle(): string
    {
        return $this->name !== '' ? $this->name : $this->tagName;
    }

    public function hasBody(): bool
    {
        return trim($this->body) !== '';
    }
}
