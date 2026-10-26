<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Identity\BranchId;
use App\Domain\Identity\UserId;

interface OperatorFavoritesRepository
{
    public function save(OperatorFavorites $favorites): void;

    /** `null` = o operador ainda não montou a lista nesta filial. */
    public function findFor(UserId $userId, BranchId $branchId): ?OperatorFavorites;
}
