<?php

declare(strict_types=1);

namespace App\Domain\Identity;

enum BranchStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
}
