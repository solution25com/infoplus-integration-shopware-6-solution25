<?php declare(strict_types=1);

namespace InfoPlusCommerce\ScheduledTask;

use InfoPlusCommerce\Service\IdMappingService;
use InfoPlusCommerce\Service\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\StateMachine\Transition;

class SyncPaidOrdersTaskHandler extends ScheduledTaskHandler
{
    private SyncService $syncService;
    private IdMappingService $idMappingService;
    private LoggerInterface $logger;

    public function __construct(
        EntityRepository     $scheduledTaskRepository,
        SyncService          $syncService,
        IdMappingService     $idMappingService,
        LoggerInterface      $logger
    )
    {
        parent::__construct($scheduledTaskRepository);
        $this->syncService = $syncService;
        $this->idMappingService = $idMappingService;
        $this->logger = $logger;
    }

    public static function getHandledMessages(): iterable
    {
        return [SyncPaidOrdersTask::class];
    }

    public function run(): void
    {
        $context = Context::createDefaultContext();
        $this->syncService->syncPaidOrders($context);
    }
}