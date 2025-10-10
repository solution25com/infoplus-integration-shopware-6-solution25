<?php

declare(strict_types=1);

namespace InfoPlusCommerce\ScheduledTask;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use InfoPlusCommerce\Service\SyncService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SyncProductInventoryTaskHandler
{
    public function __construct(
        private readonly SyncService $syncService
    ) {
    }

    public function __invoke(SyncProductInventoryTask $task): void
    {
        $this->syncService->syncInventory(Context::createCLIContext());
    }
}
