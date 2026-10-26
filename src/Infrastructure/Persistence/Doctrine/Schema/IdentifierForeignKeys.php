<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Schema;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

/**
 * Referência entre agregados é por ID (doc 02): `Station::$branchId` é um
 * BranchId, não uma Branch. Para o Doctrine isso é um campo comum — ele não
 * sabe que é chave estrangeira. Este listener completa o schema que o ORM
 * gera: todo campo cujo tipo é um ID tipado ganha a FK para a tabela dona.
 *
 * Roda quando o Doctrine monta o schema esperado (make:migration,
 * schema:validate), então migration e validação enxergam as FKs.
 */
#[AsDoctrineListener(event: ToolEvents::postGenerateSchemaTable)]
final class IdentifierForeignKeys
{
    /** tipo DBAL do campo => [tabela referenciada, ON DELETE (null = RESTRICT)] */
    private const REFERENCES = [
        'company_id' => ['companies', 'CASCADE'],
        'branch_id' => ['branches', 'CASCADE'],
        // catálogo arquiva em vez de apagar: apagar em uso deve falhar
        'category_id' => ['categories', null],
        'station_id' => ['stations', null],
    ];

    public function postGenerateSchemaTable(GenerateSchemaTableEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();
        $table = $event->getClassTable();
        $tableName = $table->getObjectName()->toString();
        $editor = null;

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $reference = self::REFERENCES[$mapping->type] ?? null;
            if (null === $reference || $metadata->isIdentifier($field)) {
                continue;
            }

            [$foreignTable, $onDelete] = $reference;
            $editor ??= $table->edit();
            $editor->addForeignKeyConstraint(new ForeignKeyConstraint(
                localColumnNames: [$mapping->columnName],
                foreignTableName: $foreignTable,
                foreignColumnNames: ['id'],
                name: "fk_{$tableName}_{$mapping->columnName}",
                options: null === $onDelete ? [] : ['onDelete' => $onDelete],
            ));
        }

        if (null !== $editor) {
            $event->setClassTable($editor->create());
        }
    }
}
