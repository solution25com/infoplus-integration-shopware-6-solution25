<?php

declare(strict_types=1);

namespace InfoPlusCommerce\ScheduledTask;

use InfoPlusCommerce\Service\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SyncPaidOrdersTaskHandler
{
    public function __construct(
        private readonly SyncService $syncService
    ) {
    }

    /**
     * @return iterable<class-string>
     */
    public static function getHandledMessages(): iterable
    {
        return [SyncPaidOrdersTask::class];
    }
    public function __invoke(SyncPaidOrdersTask $task): void
    {
        $this->syncService->syncPaidOrders(Context::createCLIContext());
    }
}
