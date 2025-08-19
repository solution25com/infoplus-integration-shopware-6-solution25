<?php declare(strict_types=1);

namespace InfoPlusCommerce\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1751447247 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1751447247;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `infoplus_id_mapping` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `entity_type` VARCHAR(50) NOT NULL,
                `shopware_id` VARCHAR(64) NOT NULL,
                `infoplus_id` INT NOT NULL,
                UNIQUE KEY `unique_mapping` (`entity_type`, `shopware_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}