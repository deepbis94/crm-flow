<?php

namespace App\Contracts;

use App\Models\CrmQueue;
use App\Models\Lead;
use App\Models\User;

interface AssignmentStrategy
{
    public function name(): string;

    public function pickAgent(CrmQueue $queue, Lead $lead): ?User;
}
