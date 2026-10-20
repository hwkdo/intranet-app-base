<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

class BackgroundActivityOutput
{
    private ?string $output = null;

    public function remember(?string $output): void
    {
        $this->output = $output !== null && trim($output) !== '' ? trim($output) : null;
    }

    public function pull(): ?string
    {
        $output = $this->output;
        $this->output = null;

        return $output;
    }
}
