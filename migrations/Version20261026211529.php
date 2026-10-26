<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M3.2 — comanda: commands, command_items, command_item_modifiers (doc 03 §3),
 * gerada por `make:migration`; FK de usuário passa a RESTRICT (inclusive
 * operator_favorites): usuário se desativa, não se apaga — o histórico fica.
 *
 * Escrito à mão: `counters` (numeração diária, doc 03 §8). Fica fora do ORM
 * (`schema_filter` no doctrine.yaml): só é acessada por SQL atômico.
 */
final class Version20261026211529 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M3.2: commands, command_items, command_item_modifiers, counters';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE counters (
                company_id CHAR(26) NOT NULL,
                branch_id CHAR(26) NOT NULL,
                scope VARCHAR(30) NOT NULL,
                value INT NOT NULL DEFAULT 0,
                PRIMARY KEY (company_id, branch_id, scope),
                CONSTRAINT fk_counters_company_id FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
                CONSTRAINT fk_counters_branch_id FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
        $this->addSql('CREATE TABLE command_item_modifiers (modifier_name VARCHAR(80) NOT NULL, price_delta_cents BIGINT NOT NULL, modifier_id CHAR(26) NOT NULL, command_item_id CHAR(26) NOT NULL, INDEX IDX_31BF10D2FD0501BD (command_item_id), INDEX IDX_31BF10D2D079F553 (modifier_id), PRIMARY KEY (modifier_id, command_item_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE command_items (company_id CHAR(26) NOT NULL, product_id CHAR(26) NOT NULL, product_variant_id CHAR(26) NOT NULL, product_name VARCHAR(150) NOT NULL, variant_name VARCHAR(80) NOT NULL, unit_price_cents BIGINT NOT NULL, station_id CHAR(26) DEFAULT NULL, quantity INT NOT NULL, modifiers_total_cents BIGINT NOT NULL, line_total_cents BIGINT NOT NULL, notes VARCHAR(255) DEFAULT NULL, status VARCHAR(30) NOT NULL, cancelled_by CHAR(26) DEFAULT NULL, cancel_reason VARCHAR(255) DEFAULT NULL, cancelled_at DATETIME(6) DEFAULT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, id CHAR(26) NOT NULL, command_id CHAR(26) NOT NULL, INDEX idx_command_items_status (command_id, status), INDEX IDX_72B6BC7E33E1689A (command_id), INDEX IDX_72B6BC7E979B1AD6 (company_id), INDEX IDX_72B6BC7E4584665A (product_id), INDEX IDX_72B6BC7EA80EF684 (product_variant_id), INDEX IDX_72B6BC7E21BDB235 (station_id), INDEX IDX_72B6BC7E48CCCEFB (cancelled_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commands (company_id CHAR(26) NOT NULL, branch_id CHAR(26) NOT NULL, display_number INT NOT NULL, business_date DATE NOT NULL, bind_type VARCHAR(20) NOT NULL, bind_ref VARCHAR(60) DEFAULT NULL, open_table_ref VARCHAR(60) DEFAULT NULL, status VARCHAR(30) NOT NULL, opened_by CHAR(26) NOT NULL, opened_at DATETIME(6) NOT NULL, closed_at DATETIME(6) DEFAULT NULL, cancel_reason VARCHAR(255) DEFAULT NULL, subtotal_cents BIGINT NOT NULL, discount_total_cents BIGINT NOT NULL, service_fee_cents BIGINT NOT NULL, total_cents BIGINT NOT NULL, paid_total_cents BIGINT NOT NULL, balance_cents BIGINT NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, id CHAR(26) NOT NULL, INDEX idx_commands_branch_status (branch_id, status), INDEX idx_commands_bind (company_id, bind_type, bind_ref), UNIQUE INDEX uniq_commands_daily_number (branch_id, business_date, display_number), UNIQUE INDEX uniq_commands_open_table (branch_id, open_table_ref), INDEX IDX_9A3E132C979B1AD6 (company_id), INDEX IDX_9A3E132CDCD6CC49 (branch_id), INDEX IDX_9A3E132CDE97F77A (opened_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE command_item_modifiers ADD CONSTRAINT FK_31BF10D2FD0501BD FOREIGN KEY (command_item_id) REFERENCES command_items (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE command_item_modifiers ADD CONSTRAINT fk_command_item_modifiers_modifier_id FOREIGN KEY (modifier_id) REFERENCES modifiers (id)');
        $this->addSql('ALTER TABLE command_items ADD CONSTRAINT FK_72B6BC7E33E1689A FOREIGN KEY (command_id) REFERENCES commands (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE command_items ADD CONSTRAINT fk_command_items_company_id FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE command_items ADD CONSTRAINT fk_command_items_product_id FOREIGN KEY (product_id) REFERENCES products (id)');
        $this->addSql('ALTER TABLE command_items ADD CONSTRAINT fk_command_items_product_variant_id FOREIGN KEY (product_variant_id) REFERENCES product_variants (id)');
        $this->addSql('ALTER TABLE command_items ADD CONSTRAINT fk_command_items_station_id FOREIGN KEY (station_id) REFERENCES stations (id)');
        $this->addSql('ALTER TABLE command_items ADD CONSTRAINT fk_command_items_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users (id)');
        $this->addSql('ALTER TABLE commands ADD CONSTRAINT fk_commands_company_id FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commands ADD CONSTRAINT fk_commands_branch_id FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commands ADD CONSTRAINT fk_commands_opened_by FOREIGN KEY (opened_by) REFERENCES users (id)');
        $this->addSql('ALTER TABLE operator_favorites DROP FOREIGN KEY `fk_operator_favorites_user_id`');
        $this->addSql('ALTER TABLE operator_favorites ADD CONSTRAINT fk_operator_favorites_user_id FOREIGN KEY (user_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE counters');
        $this->addSql('ALTER TABLE command_item_modifiers DROP FOREIGN KEY FK_31BF10D2FD0501BD');
        $this->addSql('ALTER TABLE command_item_modifiers DROP FOREIGN KEY fk_command_item_modifiers_modifier_id');
        $this->addSql('ALTER TABLE command_items DROP FOREIGN KEY FK_72B6BC7E33E1689A');
        $this->addSql('ALTER TABLE command_items DROP FOREIGN KEY fk_command_items_company_id');
        $this->addSql('ALTER TABLE command_items DROP FOREIGN KEY fk_command_items_product_id');
        $this->addSql('ALTER TABLE command_items DROP FOREIGN KEY fk_command_items_product_variant_id');
        $this->addSql('ALTER TABLE command_items DROP FOREIGN KEY fk_command_items_station_id');
        $this->addSql('ALTER TABLE command_items DROP FOREIGN KEY fk_command_items_cancelled_by');
        $this->addSql('ALTER TABLE commands DROP FOREIGN KEY fk_commands_company_id');
        $this->addSql('ALTER TABLE commands DROP FOREIGN KEY fk_commands_branch_id');
        $this->addSql('ALTER TABLE commands DROP FOREIGN KEY fk_commands_opened_by');
        $this->addSql('DROP TABLE command_item_modifiers');
        $this->addSql('DROP TABLE command_items');
        $this->addSql('DROP TABLE commands');
        $this->addSql('ALTER TABLE operator_favorites DROP FOREIGN KEY fk_operator_favorites_user_id');
        $this->addSql('ALTER TABLE operator_favorites ADD CONSTRAINT `fk_operator_favorites_user_id` FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE CASCADE');
    }
}
