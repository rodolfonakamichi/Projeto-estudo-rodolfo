<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M2.2 — agregado Product: products, product_variants, modifier_groups, modifiers (doc 03 §2).
 * Gerada por `make:migration` e revisada: sem `base_price_cents` (preço só na
 * variação) e sem `is_required` (obrigatório = min_select >= 1).
 */
final class Version20261026202617 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M2.2: products, product_variants, modifier_groups, modifiers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE modifier_groups (name VARCHAR(80) NOT NULL, min_select INT NOT NULL, max_select INT NOT NULL, sort_order INT NOT NULL, id CHAR(26) NOT NULL, product_id CHAR(26) NOT NULL, UNIQUE INDEX uniq_modifier_groups_name (product_id, name), INDEX IDX_313BC9CC4584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE modifiers (name VARCHAR(80) NOT NULL, price_delta_cents BIGINT NOT NULL, is_active TINYINT NOT NULL, sort_order INT NOT NULL, id CHAR(26) NOT NULL, modifier_group_id CHAR(26) NOT NULL, UNIQUE INDEX uniq_modifiers_name (modifier_group_id, name), INDEX IDX_D8A9FE73B990BB9 (modifier_group_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_variants (name VARCHAR(80) NOT NULL, price_cents BIGINT NOT NULL, sku VARCHAR(60) DEFAULT NULL, is_active TINYINT NOT NULL, sort_order INT NOT NULL, id CHAR(26) NOT NULL, product_id CHAR(26) NOT NULL, UNIQUE INDEX uniq_product_variants_name (product_id, name), INDEX IDX_782839764584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE products (company_id CHAR(26) NOT NULL, branch_id CHAR(26) NOT NULL, category_id CHAR(26) DEFAULT NULL, station_id CHAR(26) DEFAULT NULL, name VARCHAR(150) NOT NULL, kind VARCHAR(20) NOT NULL, track_stock TINYINT NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, id CHAR(26) NOT NULL, INDEX idx_products_branch_active (branch_id, is_active), INDEX IDX_B3BA5A5A979B1AD6 (company_id), INDEX IDX_B3BA5A5ADCD6CC49 (branch_id), INDEX IDX_B3BA5A5A12469DE2 (category_id), INDEX IDX_B3BA5A5A21BDB235 (station_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE modifier_groups ADD CONSTRAINT FK_313BC9CC4584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE modifiers ADD CONSTRAINT FK_D8A9FE73B990BB9 FOREIGN KEY (modifier_group_id) REFERENCES modifier_groups (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_variants ADD CONSTRAINT FK_782839764584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT fk_products_company_id FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT fk_products_branch_id FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT fk_products_category_id FOREIGN KEY (category_id) REFERENCES categories (id)');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT fk_products_station_id FOREIGN KEY (station_id) REFERENCES stations (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE modifier_groups DROP FOREIGN KEY FK_313BC9CC4584665A');
        $this->addSql('ALTER TABLE modifiers DROP FOREIGN KEY FK_D8A9FE73B990BB9');
        $this->addSql('ALTER TABLE product_variants DROP FOREIGN KEY FK_782839764584665A');
        $this->addSql('ALTER TABLE products DROP FOREIGN KEY fk_products_company_id');
        $this->addSql('ALTER TABLE products DROP FOREIGN KEY fk_products_branch_id');
        $this->addSql('ALTER TABLE products DROP FOREIGN KEY fk_products_category_id');
        $this->addSql('ALTER TABLE products DROP FOREIGN KEY fk_products_station_id');
        $this->addSql('DROP TABLE modifier_groups');
        $this->addSql('DROP TABLE modifiers');
        $this->addSql('DROP TABLE product_variants');
        $this->addSql('DROP TABLE products');
    }
}
