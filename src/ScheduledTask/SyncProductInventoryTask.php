<?php

declare(strict_types=1);

namespace InfoPlusCommerce\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class SyncProductInventoryTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'info_plus.sync_product_inventory';
    }

    public static function getDefaultInterval(): int
    {
        return 900; // Run every 15 minutes 900
    }
}
