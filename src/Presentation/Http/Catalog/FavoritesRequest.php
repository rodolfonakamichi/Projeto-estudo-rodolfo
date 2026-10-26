<?php

declare(strict_types=1);

namespace App\Presentation\Http\Catalog;

use App\Domain\Catalog\OperatorFavorites;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class FavoritesRequest
{
    /** @param list<string> $productIds na ordem em que aparecem no PWA */
    public function __construct(
        #[Assert\Count(max: OperatorFavorites::MAX), Assert\All([new Assert\Ulid()])] public array $productIds = [],
    ) {
    }
}
