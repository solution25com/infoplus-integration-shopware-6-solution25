<?php

declare(strict_types=1);

namespace InfoPlusCommerce\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1751447253 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1751447253;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `infoplus_category` (
                `id` BINARY(16) NOT NULL,
                `id_for_infoplus` VARCHAR(255) NOT NULL,
                `internal_id` INT NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `is_sub_category` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
