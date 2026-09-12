<?php

namespace App\Enums;

enum AssignmentStrategyName: string
{
    case RoundRobin = 'round_robin';
    case LoadBalanced = 'load_balanced';
    case SkillBased = 'skill_based';
}
