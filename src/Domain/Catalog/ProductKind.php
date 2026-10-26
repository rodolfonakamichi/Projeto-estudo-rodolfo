<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

/** `Service` (corte de cabelo, diária) chega na Fase 3; o MVP só cria `Product`. */
enum ProductKind: string
{
    case Product = 'PRODUCT';
    case Service = 'SERVICE';
}
