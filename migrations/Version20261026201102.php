<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M2.1 — catálogo: stations e categories (doc 03 §2).
 * Gerada por `make:migration` e revisada: as FKs de company_id/branch_id vêm do
 * listener IdentifierForeignKeys (referência por ID, doc 02); charset de
 * `stations` acrescentado à mão (o DBAL perde as opções ao reconstruir a tabela).
 */
final class Version20261026201102 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M2.1: stations, categories';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE categories (company_id CHAR(26) NOT NULL, branch_id CHAR(26) NOT NULL, name VARCHAR(120) NOT NULL, sort_order INT NOT NULL, archived_at DATETIME(6) DEFAULT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, id CHAR(26) NOT NULL, UNIQUE INDEX uniq_categories_branch_name (branch_id, name), INDEX IDX_3AF34668979B1AD6 (company_id), INDEX IDX_3AF34668DCD6CC49 (branch_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stations (company_id CHAR(26) NOT NULL, branch_id CHAR(26) NOT NULL, name VARCHAR(60) NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, id CHAR(26) NOT NULL, UNIQUE INDEX uniq_stations_branch_name (branch_id, name), INDEX IDX_A7F775E9979B1AD6 (company_id), INDEX IDX_A7F775E9DCD6CC49 (branch_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT fk_categories_company_id FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT fk_categories_branch_id FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stations ADD CONSTRAINT fk_stations_company_id FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stations ADD CONSTRAINT fk_stations_branch_id FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE categories DROP FOREIGN KEY fk_categories_company_id');
        $this->addSql('ALTER TABLE categories DROP FOREIGN KEY fk_categories_branch_id');
        $this->addSql('ALTER TABLE stations DROP FOREIGN KEY fk_stations_company_id');
        $this->addSql('ALTER TABLE stations DROP FOREIGN KEY fk_stations_branch_id');
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP TABLE stations');
    }
}
