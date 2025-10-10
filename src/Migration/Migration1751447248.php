<?php

declare(strict_types=1);

namespace InfoPlusCommerce\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1751447248 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1751447248;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `infoplus_order_sync` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `shopware_order_id` VARCHAR(64) NOT NULL,
                `infoplus_id` INT NOT NULL,
                `sync_date` DATETIME NOT NULL,
                `order_payment_status` VARCHAR(64) NULL,
                `order_shipping_status` VARCHAR(64) NULL,
                `order_status` VARCHAR(64) NULL,
                UNIQUE KEY `unique_order` (`shopware_order_id`, `infoplus_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
