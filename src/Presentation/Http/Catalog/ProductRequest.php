<?php

declare(strict_types=1);

namespace App\Presentation\Http\Catalog;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ProductRequest
{
    /**
     * @param list<VariantRequest>       $variants
     * @param list<ModifierGroupRequest> $modifierGroups
     */
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 150)] public string $name = '',
        #[Assert\Count(min: 1, minMessage: 'Informe ao menos uma variação (é nela que mora o preço).'), Assert\Valid] public array $variants = [],
        #[Assert\Valid] public array $modifierGroups = [],
        #[Assert\Ulid] public ?string $categoryId = null,
        #[Assert\Ulid] public ?string $stationId = null,
        public bool $trackStock = false,
    ) {
    }
}
