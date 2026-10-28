<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

use Hwkdo\IntranetAppBase\Contracts\IntranetAiGatewayInterface;
use InvalidArgumentException;

/**
 * Provider-neutrale Klassifikationsfrage für {@see IntranetAiGatewayInterface::classify()}.
 */
final class AiClassificationQuestion
{
    public const TypeBoolean = 'boolean';

    public const TypeChoice = 'choice';

    /**
     * @param  array<string, string>  $options  Boolean: Schlüssel `true`/`false`; Choice: Option => Beschreibung (mind. zwei).
     */
    private function __construct(
        public readonly string $type,
        public readonly string $instructions,
        public readonly array $options,
    ) {}

    /**
     * @param  array{true?: string, false?: string}  $criteria
     */
    public static function boolean(string $instructions, array $criteria = []): self
    {
        return new self(self::TypeBoolean, $instructions, $criteria);
    }

    /**
     * @param  array<string, string>  $options
     */
    public static function choice(string $instructions, array $options): self
    {
        if (count($options) < 2) {
            throw new InvalidArgumentException('Eine Auswahlfrage braucht mindestens zwei Optionen.');
        }

        return new self(self::TypeChoice, $instructions, $options);
    }
}
