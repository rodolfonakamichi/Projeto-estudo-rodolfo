<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration de DADOS: permissão nova no código (DefaultRoles) só vale para
 * empresas criadas depois. Esta migration concede `admin.device.manage` aos
 * papéis OWNER/ADMIN/MANAGER das empresas que já existem.
 *
 * Regra do projeto: toda permissão nova em DefaultRoles vem com uma migration assim.
 */
final class Version20261023215500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M1.3: concede admin.device.manage a OWNER/ADMIN/MANAGER existentes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT IGNORE INTO role_permissions (role_id, permission)
            SELECT id, 'admin.device.manage' FROM roles WHERE code IN ('OWNER', 'ADMIN', 'MANAGER')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM role_permissions WHERE permission = 'admin.device.manage'");
    }
}
