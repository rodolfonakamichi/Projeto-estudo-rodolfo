<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Schema;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

/**
 * Referência entre agregados é por ID (doc 02): `Station::$branchId` é um
 * BranchId, não uma Branch. Para o Doctrine isso é um campo comum — ele não
 * sabe que é chave estrangeira. Este listener completa o schema que o ORM
 * gera: todo campo cujo tipo é um ID tipado ganha a FK para a tabela dona.
 *
 * Roda quando o Doctrine monta o schema esperado (make:migration,
 * schema:validate), então migration e validação enxergam as FKs.
 *
 * Evento `postGenerateSchema` (uma vez, com todas as tabelas prontas) e não
 * `postGenerateSchemaTable`: trocar a tabela no meio da geração fazia o ORM
 * recriar o schema, e as tabelas seguintes perdiam o charset padrão.
 */
#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
final class IdentifierForeignKeys
{
    /** tipo DBAL do campo => [tabela referenciada, ON DELETE (null = RESTRICT)] */
    private const REFERENCES = [
        'company_id' => ['companies', 'CASCADE'],
        'branch_id' => ['branches', 'CASCADE'],
        // catálogo arquiva em vez de apagar: apagar em uso deve falhar
        'category_id' => ['categories', null],
        'station_id' => ['stations', null],
        // produto também não se apaga (desativa); comanda/pedido vão apontar para ele
        'product_id' => ['products', null],
        'user_id' => ['users', 'CASCADE'],
        // local também se desativa em vez de apagar (comanda de mesa aponta para ele)
        'location_id' => ['locations', null],
    ];

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $schemaEditor = $event->getSchema()->edit();

        foreach ($event->getEntityManager()->getMetadataFactory()->getAllMetadata() as $metadata) {
            $tableName = $metadata->getTableName();
            $foreignKeys = [];

            foreach ($metadata->fieldMappings as $field => $mapping) {
                $reference = self::REFERENCES[$mapping->type] ?? null;
                if (null === $reference) {
                    continue;
                }

                [$foreignTable, $onDelete] = $reference;
                // o `id` da própria tabela não é FK (mas parte de PK composta que aponta para outra tabela é)
                if ($foreignTable === $tableName) {
                    continue;
                }

                $foreignKeys[] = new ForeignKeyConstraint(
                    localColumnNames: [$mapping->columnName],
                    foreignTableName: $foreignTable,
                    foreignColumnNames: ['id'],
                    name: "fk_{$tableName}_{$mapping->columnName}",
                    options: null === $onDelete ? [] : ['onDelete' => $onDelete],
                );
            }

            if ([] !== $foreignKeys && '' !== $tableName) {
                $schemaEditor->modifyTableByUnquotedName($tableName, static function (TableEditor $table) use ($foreignKeys): void {
                    foreach ($foreignKeys as $foreignKey) {
                        $table->addForeignKeyConstraint($foreignKey);
                    }
                });
            }
        }

        $event->setSchema($schemaEditor->create());
    }
}
