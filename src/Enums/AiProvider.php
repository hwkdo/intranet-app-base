<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Enums;

enum AiProvider: string
{
    case OpenAi = 'openai';
    case Langdock = 'langdock';
    case OpenWebUi = 'openwebui';
    case Gemini = 'gemini';
    case Anthropic = 'anthropic';
    case Azure = 'azure';
    case Groq = 'groq';
    case Mistral = 'mistral';
    case Ollama = 'ollama';
    case OpenRouter = 'openrouter';
    case Xai = 'xai';

    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI',
            self::Langdock => 'Langdock',
            self::OpenWebUi => 'Open WebUI',
            self::Gemini => 'Gemini',
            self::Anthropic => 'Anthropic',
            self::Azure => 'Azure OpenAI',
            self::Groq => 'Groq',
            self::Mistral => 'Mistral',
            self::Ollama => 'Ollama',
            self::OpenRouter => 'OpenRouter',
            self::Xai => 'xAI',
        };
    }
}
