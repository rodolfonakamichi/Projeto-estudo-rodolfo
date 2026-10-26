<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M3.3 — outbox e auditoria (doc 05, doc 03 §7).
 * `domain_events` gerada por `make:migration` (entidade StoredEvent, gravada no
 * mesmo flush da mudança de estado). `audit_logs` escrita à mão: fica fora do
 * ORM (`schema_filter`), só recebe INSERT por SQL.
 */
final class Version20261026213149 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M3.3: domain_events (outbox), audit_logs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE audit_logs (
                id CHAR(26) NOT NULL,
                company_id CHAR(26) NOT NULL,
                branch_id CHAR(26) DEFAULT NULL,
                actor_id CHAR(26) DEFAULT NULL,
                actor_name VARCHAR(150) NOT NULL,
                action VARCHAR(60) NOT NULL,
                target_type VARCHAR(40) NOT NULL,
                target_id CHAR(26) NOT NULL,
                summary VARCHAR(255) NOT NULL,
                metadata JSON DEFAULT NULL,
                created_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                INDEX idx_audit_logs_target (target_type, target_id),
                INDEX idx_audit_logs_company_created (company_id, created_at),
                CONSTRAINT fk_audit_logs_company_id FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
        $this->addSql('CREATE TABLE domain_events (company_id CHAR(26) NOT NULL, branch_id CHAR(26) DEFAULT NULL, aggregate_type VARCHAR(40) NOT NULL, aggregate_id CHAR(26) NOT NULL, name VARCHAR(60) NOT NULL, payload JSON NOT NULL, occurred_at DATETIME(6) NOT NULL, published_at DATETIME(6) DEFAULT NULL, id CHAR(26) NOT NULL, INDEX idx_domain_events_pending (published_at), INDEX idx_domain_events_aggregate (aggregate_type, aggregate_id, occurred_at), INDEX IDX_3CE45B83979B1AD6 (company_id), INDEX IDX_3CE45B83DCD6CC49 (branch_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE domain_events ADD CONSTRAINT fk_domain_events_company_id FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE domain_events ADD CONSTRAINT fk_domain_events_branch_id FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('ALTER TABLE domain_events DROP FOREIGN KEY fk_domain_events_company_id');
        $this->addSql('ALTER TABLE domain_events DROP FOREIGN KEY fk_domain_events_branch_id');
        $this->addSql('DROP TABLE domain_events');
    }
}
