<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Permissões do doc 06 §2, formato `contexto.recurso.ação`. Enum (e não string
 * solta): erro de digitação em permissão vira erro de compilação/PHPStan.
 * Gravado em `role_permissions.permission` (VARCHAR 60).
 */
enum Permission: string
{
    // Comanda
    case CommandOpen = 'command.open';
    case CommandItemAdd = 'command.item.add';
    case CommandItemCancel = 'command.item.cancel';
    case CommandItemCancelInProduction = 'command.item.cancel_in_production';
    case CommandDiscountApply = 'command.discount.apply';
    case CommandDiscountAuthorize = 'command.discount.authorize';
    case CommandBillRequest = 'command.bill.request';
    case CommandSplit = 'command.split';
    case CommandClose = 'command.close';
    case CommandCancel = 'command.cancel';
    case CommandReopen = 'command.reopen';
    case CommandTransfer = 'command.transfer';

    // Pedido / Produção
    case OrderSend = 'order.send';
    case OrderCancel = 'order.cancel';
    case ProductionStart = 'production.start';
    case ProductionReady = 'production.ready';
    case ProductionDeliver = 'production.deliver';
    case ProductionTicketCancel = 'production.ticket.cancel';

    // Pagamento / Caixa
    case PaymentCreate = 'payment.create';
    case PaymentRefund = 'payment.refund';
    case CashOpen = 'cash.open';
    case CashWithdraw = 'cash.withdraw';
    case CashDeposit = 'cash.deposit';
    case CashClose = 'cash.close';
    case CashCloseViewExpected = 'cash.close.view_expected';

    // Catálogo / Estoque
    case CatalogProductManage = 'catalog.product.manage';
    case CatalogPriceManage = 'catalog.price.manage';
    case CatalogStationManage = 'catalog.station.manage';
    case StockMovementCreate = 'stock.movement.create';
    case StockAdjust = 'stock.adjust';
    case StockRecipeManage = 'stock.recipe.manage';

    // Administração / Relatórios
    case AdminUserManage = 'admin.user.manage';
    case AdminBranchManage = 'admin.branch.manage';
    case AdminCapabilityManage = 'admin.capability.manage';
    case ReportSalesView = 'report.sales.view';
    case ReportAuditView = 'report.audit.view';
    case ReportProductionView = 'report.production.view';
}
