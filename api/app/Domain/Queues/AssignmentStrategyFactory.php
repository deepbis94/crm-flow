<?php

namespace App\Domain\Queues;

use App\Contracts\AssignmentStrategy;
use App\Enums\AssignmentStrategyName;
use InvalidArgumentException;

final class AssignmentStrategyFactory
{
    /**
     * @param  array<string, AssignmentStrategy>  $strategies
     */
    public function __construct(private readonly array $strategies)
    {
    }

    public function make(AssignmentStrategyName|string $name): AssignmentStrategy
    {
        $key = $name instanceof AssignmentStrategyName ? $name->value : $name;
        $strategy = $this->strategies[$key] ?? null;

        if (! $strategy) {
            throw new InvalidArgumentException("Unknown assignment strategy: {$key}");
        }

        return $strategy;
    }
}
