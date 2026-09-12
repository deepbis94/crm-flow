<?php

namespace App\Enums;

enum LeadState: string
{
    case New = 'new';
    case Queued = 'queued';
    case Claimed = 'claimed';
    case Working = 'working';
    case Qualified = 'qualified';
    case Disqualified = 'disqualified';
    case Lost = 'lost';

    /**
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::New => [self::Queued],
            self::Queued => [self::Claimed],
            self::Claimed => [self::Working, self::Queued],
            self::Working => [self::Qualified, self::Disqualified, self::Lost, self::Queued],
            self::Lost => [self::Queued],
            self::Qualified, self::Disqualified => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Claimed, self::Working], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Qualified, self::Disqualified], true);
    }
}
