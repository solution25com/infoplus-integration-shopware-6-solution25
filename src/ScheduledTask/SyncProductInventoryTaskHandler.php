<?php declare(strict_types=1);

namespace InfoPlusCommerce\ScheduledTask;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use InfoPlusCommerce\Service\SyncService;
use InfoPlusCommerce\Service\IdMappingService;
use Psr\Log\LoggerInterface;

class SyncProductInventoryTaskHandler extends ScheduledTaskHandler
{
    private SyncService $syncService;
    private IdMappingService $idMappingService;
    private EntityRepository $productRepository;
    private LoggerInterface $logger;

    public function __construct(
        EntityRepository $scheduledTaskRepository,
        SyncService $syncService,
        IdMappingService $idMappingService,
        EntityRepository $productRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->syncService = $syncService;
        $this->idMappingService = $idMappingService;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    public static function getHandledMessages(): iterable
    {
        return [SyncProductInventoryTask::class];
    }

    public function run(): void
    {
        $context = Context::createDefaultContext();
        $this->syncService->syncInventory($context);
    }
}