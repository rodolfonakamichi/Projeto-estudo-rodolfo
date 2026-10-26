<?php

declare(strict_types=1);

namespace App\Application\Attendance\Command;

use App\Application\Identity\Authorization\BranchAuthorizer;
use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Attendance\CommandId;
use App\Domain\Attendance\CommandItemId;
use App\Domain\Attendance\CommandRepository;
use App\Domain\Attendance\Quantity;
use App\Domain\Catalog\ModifierId;
use App\Domain\Catalog\ProductId;
use App\Domain\Catalog\ProductNotFound;
use App\Domain\Catalog\ProductRepository;
use App\Domain\Catalog\ProductVariantId;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use Psr\Clock\ClockInterface;

/**
 * Coordena dois contextos: o Catálogo VALIDA a escolha e devolve os preços de
 * agora (`Product::select`); a Comanda COPIA e recalcula. Cada agregado cuida
 * das suas regras; o caso de uso só os apresenta um ao outro.
 */
final readonly class AddItemToCommandHandler implements CommandHandler
{
    public function __construct(
        private BranchAuthorizer $authorizer,
        private CommandRepository $commands,
        private ProductRepository $products,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AddItemToCommand $command): void
    {
        $tab = $this->commands->get(CommandId::fromString($command->commandId));
        $this->authorizer->authorize(UserId::fromString($command->actorId), $tab->branchId(), Permission::CommandItemAdd);

        $productId = ProductId::fromString($command->productId);
        $product = $this->products->get($productId);
        if (!$product->branchId()->equals($tab->branchId())) {
            throw ProductNotFound::withId($productId);
        }

        $selection = $product->select(
            ProductVariantId::fromString($command->variantId),
            array_map(static fn (string $id): ModifierId => ModifierId::fromString($id), $command->modifierIds),
        );

        $tab->addItem(CommandItemId::fromString($command->itemId), $selection, Quantity::of($command->quantity), $command->notes, UserId::fromString($command->actorId), $this->clock->now());
    }
}
