<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Traits;

use Hwkdo\IntranetAppBase\Enums\DocumentParseEngine;

trait HasDocumentParseSettingsFields
{
    public function documentParseEngine(): ?DocumentParseEngine
    {
        return $this->documentParseEngineOverride;
    }

    public function documentParseTier(): ?string
    {
        $value = $this->documentParseTierOverride;
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
