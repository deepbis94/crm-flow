<?php

namespace App\Exceptions;

use RuntimeException;

class RateLimitedException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds = 1)
    {
        parent::__construct('Rate limit exceeded.');
    }
}
