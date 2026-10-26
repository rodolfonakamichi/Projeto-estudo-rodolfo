<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M2.5 — favoritos do operador: operator_favorites (1 lista por usuário e
 * filial) e operator_favorite_items (PK composta lista + produto).
 * Gerada por `make:migration`.
 */
final class Version20261026205421 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M2.5: operator_favorites, operator_favorite_items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE operator_favorite_items (position INT NOT NULL, product_id CHAR(26) NOT NULL, operator_favorites_id CHAR(26) NOT NULL, INDEX IDX_CC9CE413B11E123 (operator_favorites_id), INDEX IDX_CC9CE4134584665A (product_id), PRIMARY KEY (product_id, operator_favorites_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE operator_favorites (company_id CHAR(26) NOT NULL, branch_id CHAR(26) NOT NULL, user_id CHAR(26) NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, id CHAR(26) NOT NULL, UNIQUE INDEX uniq_operator_favorites_user_branch (user_id, branch_id), INDEX IDX_22B19F32979B1AD6 (company_id), INDEX IDX_22B19F32DCD6CC49 (branch_id), INDEX IDX_22B19F32A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE operator_favorite_items ADD CONSTRAINT FK_CC9CE413B11E123 FOREIGN KEY (operator_favorites_id) REFERENCES operator_favorites (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE operator_favorite_items ADD CONSTRAINT fk_operator_favorite_items_product_id FOREIGN KEY (product_id) REFERENCES products (id)');
        $this->addSql('ALTER TABLE operator_favorites ADD CONSTRAINT fk_operator_favorites_company_id FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE operator_favorites ADD CONSTRAINT fk_operator_favorites_branch_id FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE operator_favorites ADD CONSTRAINT fk_operator_favorites_user_id FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operator_favorite_items DROP FOREIGN KEY FK_CC9CE413B11E123');
        $this->addSql('ALTER TABLE operator_favorite_items DROP FOREIGN KEY fk_operator_favorite_items_product_id');
        $this->addSql('ALTER TABLE operator_favorites DROP FOREIGN KEY fk_operator_favorites_company_id');
        $this->addSql('ALTER TABLE operator_favorites DROP FOREIGN KEY fk_operator_favorites_branch_id');
        $this->addSql('ALTER TABLE operator_favorites DROP FOREIGN KEY fk_operator_favorites_user_id');
        $this->addSql('DROP TABLE operator_favorite_items');
        $this->addSql('DROP TABLE operator_favorites');
    }
}
