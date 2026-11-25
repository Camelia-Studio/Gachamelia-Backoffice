<?php

declare(strict_types=1);

namespace App\Enum;

enum ProgressionEventType: string
{
    case Rank = 'rank';
    case Quarter = 'quarter';
    case Constellation = 'constellation';
}
