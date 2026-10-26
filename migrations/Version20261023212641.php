<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M1 — agregado Company: companies, branches, company_capabilities (doc 03 §1).
 * Gerada por `make:migration` a partir do mapeamento XML e revisada contra o doc 03.
 */
final class Version20261023212641 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M1: companies, branches, company_capabilities';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE branches (name VARCHAR(150) NOT NULL, timezone VARCHAR(40) NOT NULL, status VARCHAR(30) NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, id CHAR(26) NOT NULL, company_id CHAR(26) NOT NULL, UNIQUE INDEX uniq_branches_company_name (company_id, name), INDEX IDX_D760D16F979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE companies (name VARCHAR(150) NOT NULL, slug VARCHAR(80) NOT NULL, status VARCHAR(30) NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, id CHAR(26) NOT NULL, UNIQUE INDEX uniq_companies_slug (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE company_capabilities (capability VARCHAR(40) NOT NULL, enabled TINYINT NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, id CHAR(26) NOT NULL, company_id CHAR(26) NOT NULL, UNIQUE INDEX uniq_company_capabilities (company_id, capability), INDEX IDX_E3F6E733979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE branches ADD CONSTRAINT FK_D760D16F979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company_capabilities ADD CONSTRAINT FK_E3F6E733979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE branches DROP FOREIGN KEY FK_D760D16F979B1AD6');
        $this->addSql('ALTER TABLE company_capabilities DROP FOREIGN KEY FK_E3F6E733979B1AD6');
        $this->addSql('DROP TABLE branches');
        $this->addSql('DROP TABLE companies');
        $this->addSql('DROP TABLE company_capabilities');
    }
}
