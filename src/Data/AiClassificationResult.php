<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

final class AiClassificationResult
{
    /**
     * @param  array<string, float>  $probabilities  Boolean-Fragen: Wahrscheinlichkeit für „true“.
     * @param  array<string, array{choice: string, probabilities: array<string, float>, confidence: ?float}>  $choices
     */
    public function __construct(
        public readonly array $probabilities = [],
        public readonly array $choices = [],
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
    ) {}

    public function probability(string $key): ?float
    {
        return $this->probabilities[$key] ?? null;
    }

    public function choice(string $key): ?string
    {
        return $this->choices[$key]['choice'] ?? null;
    }

    /**
     * @return array<string, float>
     */
    public function choiceProbabilities(string $key): array
    {
        return $this->choices[$key]['probabilities'] ?? [];
    }

    /**
     * @return array{probabilities: array<string, float>, choices: array<string, mixed>, provider: ?string, model: ?string}
     */
    public function toArray(): array
    {
        return [
            'probabilities' => $this->probabilities,
            'choices' => $this->choices,
            'provider' => $this->provider,
            'model' => $this->model,
        ];
    }
}
