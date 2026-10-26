<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M3.1 — locais da filial (mesas, balcão, áreas): tabela `locations` (doc 03 §3)
 * gerada por `make:migration`, mais a parte de DADOS escrita à mão: permissão
 * nova `admin.location.manage` para OWNER/ADMIN/MANAGER das empresas existentes
 * (regra do projeto: permissão nova em DefaultRoles vem com migration de dados).
 */
final class Version20261026210436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M3.1: locations + admin.location.manage para OWNER/ADMIN/MANAGER existentes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE locations (company_id CHAR(26) NOT NULL, branch_id CHAR(26) NOT NULL, type VARCHAR(20) NOT NULL, label VARCHAR(60) NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, id CHAR(26) NOT NULL, UNIQUE INDEX uniq_locations_branch_label (branch_id, label), INDEX IDX_17E64ABA979B1AD6 (company_id), INDEX IDX_17E64ABADCD6CC49 (branch_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE locations ADD CONSTRAINT fk_locations_company_id FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE locations ADD CONSTRAINT fk_locations_branch_id FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
            INSERT IGNORE INTO role_permissions (role_id, permission)
            SELECT id, 'admin.location.manage' FROM roles WHERE code IN ('OWNER', 'ADMIN', 'MANAGER')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM role_permissions WHERE permission = 'admin.location.manage'");
        $this->addSql('ALTER TABLE locations DROP FOREIGN KEY fk_locations_company_id');
        $this->addSql('ALTER TABLE locations DROP FOREIGN KEY fk_locations_branch_id');
        $this->addSql('DROP TABLE locations');
    }
}
