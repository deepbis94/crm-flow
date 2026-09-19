<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /**
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Pending => [self::Succeeded, self::Failed],
            self::Succeeded => [self::Refunded],
            self::Failed, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }
}
