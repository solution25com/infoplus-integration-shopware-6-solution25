<?php

namespace InfoPlusCommerce\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1751448000 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1751448000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `infoplus_field_definition`
            ADD COLUMN `show_in_storefront` BOOLEAN NOT NULL DEFAULT 0 AFTER `active`
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // No destructive update
    }
}

