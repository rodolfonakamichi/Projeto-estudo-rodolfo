<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M2.4 — busca rápida no cardápio: índice FULLTEXT em products.name (doc 03 §2).
 * Gerada por `make:migration`.
 */
final class Version20261026203908 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M2.4: FULLTEXT products.name';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE FULLTEXT INDEX ft_products_name ON products (name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX ft_products_name ON products');
    }
}
