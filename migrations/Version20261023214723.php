<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M1.3 — autenticação: api_tokens (token de usuário) e devices (aparelho pareado).
 * Gerada por `make:migration` a partir do mapeamento XML e revisada contra o doc 03.
 */
final class Version20261023214723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M1.3: api_tokens, devices';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE api_tokens (name VARCHAR(80) NOT NULL, token_hash CHAR(64) NOT NULL, last_used_at DATETIME(6) DEFAULT NULL, expires_at DATETIME(6) DEFAULT NULL, revoked_at DATETIME(6) DEFAULT NULL, created_at DATETIME(6) NOT NULL, id CHAR(26) NOT NULL, company_id CHAR(26) NOT NULL, user_id CHAR(26) NOT NULL, UNIQUE INDEX uniq_api_tokens_hash (token_hash), INDEX IDX_2CAD560E979B1AD6 (company_id), INDEX IDX_2CAD560EA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE devices (name VARCHAR(80) NOT NULL, token_hash CHAR(64) NOT NULL, last_used_at DATETIME(6) DEFAULT NULL, revoked_at DATETIME(6) DEFAULT NULL, created_at DATETIME(6) NOT NULL, id CHAR(26) NOT NULL, company_id CHAR(26) NOT NULL, branch_id CHAR(26) NOT NULL, paired_by CHAR(26) NOT NULL, UNIQUE INDEX uniq_devices_hash (token_hash), INDEX IDX_11074E9A979B1AD6 (company_id), INDEX IDX_11074E9ADCD6CC49 (branch_id), INDEX IDX_11074E9AAE4BE3FB (paired_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE api_tokens ADD CONSTRAINT FK_2CAD560E979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE api_tokens ADD CONSTRAINT FK_2CAD560EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE devices ADD CONSTRAINT FK_11074E9A979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE devices ADD CONSTRAINT FK_11074E9ADCD6CC49 FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE devices ADD CONSTRAINT FK_11074E9AAE4BE3FB FOREIGN KEY (paired_by) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_tokens DROP FOREIGN KEY FK_2CAD560E979B1AD6');
        $this->addSql('ALTER TABLE api_tokens DROP FOREIGN KEY FK_2CAD560EA76ED395');
        $this->addSql('ALTER TABLE devices DROP FOREIGN KEY FK_11074E9A979B1AD6');
        $this->addSql('ALTER TABLE devices DROP FOREIGN KEY FK_11074E9ADCD6CC49');
        $this->addSql('ALTER TABLE devices DROP FOREIGN KEY FK_11074E9AAE4BE3FB');
        $this->addSql('DROP TABLE api_tokens');
        $this->addSql('DROP TABLE devices');
    }
}
