<?php

namespace InfoPlusCommerce\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1751447254 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1751447254;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `infoplus_field_definition` (
                `id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(255) NOT NULL,
                `label` VARCHAR(255) NOT NULL,
                `type` ENUM("text", "textarea", "number", "money", "boolean", "select") NOT NULL,
                `is_required` BOOLEAN NOT NULL DEFAULT 0,
                `options` JSON NULL,
                `position` INT NOT NULL DEFAULT 0,
                `active` BOOLEAN NOT NULL DEFAULT 1,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                UNIQUE KEY `uniq_technical_name` (`technical_name`),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // No destructive update
    }
}
