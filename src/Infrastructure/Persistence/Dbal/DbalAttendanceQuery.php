<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dbal;

use App\Application\Attendance\AttendanceQuery;
use App\Application\Attendance\CommandItemView;
use App\Application\Attendance\CommandSummaryView;
use App\Application\Attendance\CommandView;
use App\Application\Attendance\LocationView;
use App\Domain\Attendance\CommandId;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use Doctrine\DBAL\Connection;

/** Read model do atendimento: SQL direto, `company_id` sempre no WHERE. */
final readonly class DbalAttendanceQuery implements AttendanceQuery
{
    public function __construct(private Connection $db)
    {
    }

    public function locations(CompanyId $companyId, BranchId $branchId): array
    {
        $rows = $this->db->fetchAllAssociative(<<<'SQL'
            SELECT id, type, label, is_active
              FROM locations
             WHERE company_id = :company AND branch_id = :branch
             ORDER BY type, label
            SQL, ['company' => $companyId->toString(), 'branch' => $branchId->toString()]);

        return array_map(
            static fn (array $row): LocationView => new LocationView((string) $row['id'], (string) $row['type'], (string) $row['label'], (bool) $row['is_active']),
            $rows,
        );
    }

    public function activeCommands(CompanyId $companyId, BranchId $branchId): array
    {
        $rows = $this->db->fetchAllAssociative(<<<'SQL'
            SELECT c.id, c.display_number, c.bind_type, c.bind_ref, l.label AS bind_label, c.status, c.total_cents, c.opened_at,
                   (SELECT COUNT(*) FROM command_items i WHERE i.command_id = c.id AND i.status = 'ACTIVE') AS active_items
              FROM commands c
              LEFT JOIN locations l ON c.bind_type = 'MESA' AND l.id = c.bind_ref
             WHERE c.company_id = :company AND c.branch_id = :branch
               AND c.status NOT IN ('CLOSED', 'CANCELLED')
             ORDER BY c.display_number
            SQL, ['company' => $companyId->toString(), 'branch' => $branchId->toString()]);

        return array_map(static fn (array $row): CommandSummaryView => new CommandSummaryView(
            (string) $row['id'],
            (int) $row['display_number'],
            (string) $row['bind_type'],
            self::nullableString($row['bind_ref']),
            self::nullableString($row['bind_label']),
            (string) $row['status'],
            (int) $row['total_cents'],
            (int) $row['active_items'],
            (string) $row['opened_at'],
        ), $rows);
    }

    public function command(CompanyId $companyId, CommandId $commandId): ?CommandView
    {
        $row = $this->db->fetchAssociative(<<<'SQL'
            SELECT c.*, l.label AS bind_label
              FROM commands c
              LEFT JOIN locations l ON c.bind_type = 'MESA' AND l.id = c.bind_ref
             WHERE c.company_id = :company AND c.id = :command
            SQL, ['company' => $companyId->toString(), 'command' => $commandId->toString()]);

        if (false === $row) {
            return null;
        }

        $modifiers = [];
        foreach ($this->db->fetchAllAssociative(
            'SELECT m.command_item_id, m.modifier_id, m.modifier_name, m.price_delta_cents
               FROM command_item_modifiers m JOIN command_items i ON i.id = m.command_item_id
              WHERE i.command_id = :command ORDER BY m.modifier_name',
            ['command' => $commandId->toString()],
        ) as $modifier) {
            $modifiers[(string) $modifier['command_item_id']][] = [
                'modifierId' => (string) $modifier['modifier_id'],
                'name' => (string) $modifier['modifier_name'],
                'priceDeltaCents' => (int) $modifier['price_delta_cents'],
            ];
        }

        $items = array_map(static fn (array $item): CommandItemView => new CommandItemView(
            (string) $item['id'],
            (string) $item['product_id'],
            (string) $item['product_variant_id'],
            $item['variant_name'] === $item['product_name'] ? (string) $item['product_name'] : "{$item['product_name']} {$item['variant_name']}",
            (int) $item['quantity'],
            (int) $item['unit_price_cents'],
            (int) $item['modifiers_total_cents'],
            (int) $item['line_total_cents'],
            self::nullableString($item['notes']),
            (string) $item['status'],
            self::nullableString($item['cancel_reason']),
            $modifiers[(string) $item['id']] ?? [],
        ), $this->db->fetchAllAssociative('SELECT * FROM command_items WHERE command_id = :command ORDER BY id', ['command' => $commandId->toString()]));

        return new CommandView(
            (string) $row['id'],
            (int) $row['display_number'],
            (string) $row['business_date'],
            (string) $row['bind_type'],
            self::nullableString($row['bind_ref']),
            self::nullableString($row['bind_label']),
            (string) $row['status'],
            (string) $row['opened_by'],
            (string) $row['opened_at'],
            (int) $row['subtotal_cents'],
            (int) $row['discount_total_cents'],
            (int) $row['service_fee_cents'],
            (int) $row['total_cents'],
            (int) $row['paid_total_cents'],
            (int) $row['balance_cents'],
            $items,
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return null === $value ? null : (string) $value;
    }
}
