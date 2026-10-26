<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Shared\Percentage;

/**
 * Papéis padrão de toda empresa nova: a matriz do doc 06 §3 em código
 * (`▲` = concedida; o limite é aplicado no contexto, ex.: faixa de desconto §4).
 * Gerado a partir da tabela do doc 06 — se a matriz mudar lá, mude aqui.
 */
final class DefaultRoles
{
    /** @return list<Role> */
    public static function for(Company $company): array
    {
        $roles = [];
        foreach (self::matrix() as $code => [$name, $permissions, $maxDiscount]) {
            $roles[] = Role::create(RoleId::generate(), $company, RoleCode::fromString($code), $name, $permissions, $maxDiscount);
        }

        return $roles;
    }

    /**
     * @return array<string, array{string, list<Permission>, ?Percentage}> código => [nome, permissões, limite de desconto (null = sem limite)]
     */
    public static function matrix(): array
    {
        return [
            'OWNER' => ['Dono', [
                Permission::CommandOpen,
                Permission::CommandItemAdd,
                Permission::CommandItemCancel,
                Permission::CommandItemCancelInProduction,
                Permission::CommandDiscountApply,
                Permission::CommandDiscountAuthorize,
                Permission::CommandBillRequest,
                Permission::CommandSplit,
                Permission::CommandClose,
                Permission::CommandCancel,
                Permission::CommandReopen,
                Permission::OrderSend,
                Permission::OrderCancel,
                Permission::ProductionStart,
                Permission::ProductionReady,
                Permission::ProductionDeliver,
                Permission::ProductionTicketCancel,
                Permission::PaymentCreate,
                Permission::PaymentRefund,
                Permission::CashOpen,
                Permission::CashWithdraw,
                Permission::CashDeposit,
                Permission::CashClose,
                Permission::CashCloseViewExpected,
                Permission::CatalogProductManage,
                Permission::CatalogPriceManage,
                Permission::CatalogStationManage,
                Permission::StockMovementCreate,
                Permission::StockAdjust,
                Permission::StockRecipeManage,
                Permission::AdminUserManage,
                Permission::AdminBranchManage,
                Permission::AdminCapabilityManage,
                Permission::ReportSalesView,
                Permission::ReportAuditView,
                Permission::ReportProductionView,
                Permission::AdminDeviceManage,
                Permission::AdminLocationManage,
            ], null],
            'ADMIN' => ['Administrador', [
                Permission::CommandOpen,
                Permission::CommandItemAdd,
                Permission::CommandItemCancel,
                Permission::CommandItemCancelInProduction,
                Permission::CommandDiscountApply,
                Permission::CommandDiscountAuthorize,
                Permission::CommandBillRequest,
                Permission::CommandSplit,
                Permission::CommandClose,
                Permission::CommandCancel,
                Permission::CommandReopen,
                Permission::OrderSend,
                Permission::OrderCancel,
                Permission::ProductionStart,
                Permission::ProductionReady,
                Permission::ProductionDeliver,
                Permission::ProductionTicketCancel,
                Permission::PaymentCreate,
                Permission::PaymentRefund,
                Permission::CashOpen,
                Permission::CashWithdraw,
                Permission::CashDeposit,
                Permission::CashClose,
                Permission::CatalogProductManage,
                Permission::CatalogPriceManage,
                Permission::CatalogStationManage,
                Permission::StockMovementCreate,
                Permission::StockAdjust,
                Permission::StockRecipeManage,
                Permission::AdminUserManage,
                Permission::AdminBranchManage,
                Permission::AdminCapabilityManage,
                Permission::ReportSalesView,
                Permission::ReportAuditView,
                Permission::ReportProductionView,
                Permission::AdminDeviceManage,
                Permission::AdminLocationManage,
            ], null],
            'MANAGER' => ['Gerente', [
                Permission::CommandOpen,
                Permission::CommandItemAdd,
                Permission::CommandItemCancel,
                Permission::CommandItemCancelInProduction,
                Permission::CommandDiscountApply,
                Permission::CommandDiscountAuthorize,
                Permission::CommandBillRequest,
                Permission::CommandSplit,
                Permission::CommandClose,
                Permission::CommandCancel,
                Permission::CommandReopen,
                Permission::OrderSend,
                Permission::OrderCancel,
                Permission::ProductionStart,
                Permission::ProductionReady,
                Permission::ProductionDeliver,
                Permission::ProductionTicketCancel,
                Permission::PaymentCreate,
                Permission::PaymentRefund,
                Permission::CashOpen,
                Permission::CashWithdraw,
                Permission::CashDeposit,
                Permission::CashClose,
                Permission::CatalogProductManage,
                Permission::CatalogPriceManage,
                Permission::CatalogStationManage,
                Permission::StockMovementCreate,
                Permission::StockAdjust,
                Permission::StockRecipeManage,
                Permission::ReportSalesView,
                Permission::ReportAuditView,
                Permission::ReportProductionView,
                Permission::AdminDeviceManage,
                Permission::AdminLocationManage,
            ], Percentage::fromHundredths(2000)],
            'CASHIER' => ['Caixa', [
                Permission::CommandOpen,
                Permission::CommandItemAdd,
                Permission::CommandItemCancel,
                Permission::CommandItemCancelInProduction,
                Permission::CommandDiscountApply,
                Permission::CommandBillRequest,
                Permission::CommandSplit,
                Permission::CommandClose,
                Permission::OrderSend,
                Permission::OrderCancel,
                Permission::ProductionDeliver,
                Permission::PaymentCreate,
                Permission::CashOpen,
                Permission::CashWithdraw,
                Permission::CashDeposit,
                Permission::CashClose,
                Permission::ReportSalesView,
            ], Percentage::fromHundredths(1000)],
            'WAITER' => ['Garçom', [
                Permission::CommandOpen,
                Permission::CommandItemAdd,
                Permission::CommandItemCancel,
                Permission::CommandDiscountApply,
                Permission::CommandBillRequest,
                Permission::CommandSplit,
                Permission::OrderSend,
                Permission::ProductionDeliver,
            ], Percentage::fromHundredths(500)],
            'KITCHEN' => ['Cozinha', [
                Permission::CommandItemCancelInProduction,
                Permission::ProductionStart,
                Permission::ProductionReady,
                Permission::ProductionDeliver,
                Permission::ProductionTicketCancel,
                Permission::ReportProductionView,
            ], null],
            'BAR' => ['Bar', [
                Permission::CommandItemCancelInProduction,
                Permission::ProductionStart,
                Permission::ProductionReady,
                Permission::ProductionDeliver,
                Permission::ProductionTicketCancel,
                Permission::ReportProductionView,
            ], null],
            'STOCK' => ['Estoque', [
                Permission::StockMovementCreate,
                Permission::StockAdjust,
                Permission::StockRecipeManage,
            ], null],
            'AUDITOR' => ['Auditor', [
                Permission::ReportSalesView,
                Permission::ReportAuditView,
                Permission::ReportProductionView,
            ], null],
        ];
    }
}
