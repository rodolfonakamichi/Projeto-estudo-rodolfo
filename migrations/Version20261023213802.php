<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M1.2 — agregados User e Role: users, roles, role_permissions, user_roles (doc 03 §1).
 * Gerada por `make:migration` a partir do mapeamento XML e revisada contra o doc 03.
 */
final class Version20261023213802 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M1.2: users, roles, role_permissions, user_roles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE role_permissions (permission VARCHAR(60) NOT NULL, role_id CHAR(26) NOT NULL, INDEX IDX_1FBA94E6D60322AC (role_id), PRIMARY KEY (permission, role_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE roles (code VARCHAR(30) NOT NULL, name VARCHAR(80) NOT NULL, max_discount_percent NUMERIC(5, 2) DEFAULT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, id CHAR(26) NOT NULL, company_id CHAR(26) NOT NULL, UNIQUE INDEX uniq_roles_company_code (company_id, code), INDEX IDX_B63E2EC7979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_roles (id CHAR(26) NOT NULL, user_id CHAR(26) NOT NULL, role_id CHAR(26) NOT NULL, branch_id CHAR(26) DEFAULT NULL, UNIQUE INDEX uniq_user_roles_scope (user_id, role_id, branch_id), INDEX IDX_54FCD59FA76ED395 (user_id), INDEX IDX_54FCD59FD60322AC (role_id), INDEX IDX_54FCD59FDCD6CC49 (branch_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (name VARCHAR(150) NOT NULL, email VARCHAR(190) NOT NULL, password_hash VARCHAR(255) NOT NULL, pin_hash VARCHAR(255) DEFAULT NULL, status VARCHAR(30) NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, id CHAR(26) NOT NULL, company_id CHAR(26) NOT NULL, UNIQUE INDEX uniq_users_company_email (company_id, email), INDEX IDX_1483A5E9979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE role_permissions ADD CONSTRAINT FK_1FBA94E6D60322AC FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE roles ADD CONSTRAINT FK_B63E2EC7979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59FD60322AC FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59FDCD6CC49 FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE role_permissions DROP FOREIGN KEY FK_1FBA94E6D60322AC');
        $this->addSql('ALTER TABLE roles DROP FOREIGN KEY FK_B63E2EC7979B1AD6');
        $this->addSql('ALTER TABLE user_roles DROP FOREIGN KEY FK_54FCD59FA76ED395');
        $this->addSql('ALTER TABLE user_roles DROP FOREIGN KEY FK_54FCD59FD60322AC');
        $this->addSql('ALTER TABLE user_roles DROP FOREIGN KEY FK_54FCD59FDCD6CC49');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9979B1AD6');
        $this->addSql('DROP TABLE role_permissions');
        $this->addSql('DROP TABLE roles');
        $this->addSql('DROP TABLE user_roles');
        $this->addSql('DROP TABLE users');
    }
}
