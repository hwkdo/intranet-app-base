<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Traits;

use Hwkdo\IntranetAppBase\Data\Attributes\Description;
use Hwkdo\IntranetAppBase\Enums\AiProvider;

trait HasAiSettingsFields
{
    #[Description('KI-Text-Provider überschreiben (leer = Intranet-Base-Default)')]
    public ?AiProvider $aiTextProviderOverride = null;

    #[Description('KI-Text-Modell überschreiben (leer = Base- bzw. Provider-Default)')]
    public ?string $aiTextModelOverride = null;

    #[Description('KI-Bild-Provider überschreiben (leer = Intranet-Base-Default)')]
    public ?AiProvider $aiImageProviderOverride = null;

    #[Description('KI-Bild-Modell überschreiben (leer = Base- bzw. Provider-Default)')]
    public ?string $aiImageModelOverride = null;

    public function textProviderOverride(): ?AiProvider
    {
        return $this->aiTextProviderOverride;
    }

    public function textModelOverride(): ?string
    {
        return $this->blankToNull($this->aiTextModelOverride);
    }

    public function imageProviderOverride(): ?AiProvider
    {
        return $this->aiImageProviderOverride;
    }

    public function imageModelOverride(): ?string
    {
        return $this->blankToNull($this->aiImageModelOverride);
    }

    private function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
