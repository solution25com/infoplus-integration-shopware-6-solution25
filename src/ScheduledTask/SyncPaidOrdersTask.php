<?php declare(strict_types=1);

namespace InfoPlusCommerce\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class SyncPaidOrdersTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'info_plus.sync_paid_orders';
    }

    public static function getDefaultInterval(): int
    {
        return 300; // Run every 5 minutes
    }
}