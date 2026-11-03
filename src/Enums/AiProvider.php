<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Enums;

enum AiProvider: string
{
    case OpenAi = 'openai';
    case Langdock = 'langdock';
    case OpenWebUi = 'openwebui';
    case GemmaLlamaCpp = 'gemma-llama-cpp';
    case Bifrost = 'bifrost';
    case ComfyUi = 'comfyui';

    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI',
            self::Langdock => 'Langdock',
            self::OpenWebUi => 'Open WebUI',
            self::GemmaLlamaCpp => 'Gemma (llama.cpp)',
            self::Bifrost => 'Bifrost',
            self::ComfyUi => 'ComfyUI (Images)',
        };
    }
}
