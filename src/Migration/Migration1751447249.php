<?php

declare(strict_types=1);

namespace InfoPlusCommerce\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1751447249 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1751447249;
    }

    public function update(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('infoplus_id_mapping');

        $queries = [];
        if (!isset($columns['created_at'])) {
            $queries[] = 'ADD `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP';
        }
        if (!isset($columns['updated_at'])) {
            $queries[] = 'ADD `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
        }

        if (!empty($queries)) {
            $connection->executeStatement(
                'ALTER TABLE `infoplus_id_mapping` ' . implode(', ', $queries)
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
