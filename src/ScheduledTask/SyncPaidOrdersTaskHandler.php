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
    private EntityRepository $orderRepository;
    private StateMachineRegistry $stateMachineRegistry;
    private LoggerInterface $logger;

    public function __construct(
        EntityRepository     $scheduledTaskRepository,
        SyncService          $syncService,
        IdMappingService     $idMappingService,
        EntityRepository     $orderRepository,
        StateMachineRegistry $stateMachineRegistry,
        LoggerInterface      $logger
    )
    {
        parent::__construct($scheduledTaskRepository);
        $this->syncService = $syncService;
        $this->idMappingService = $idMappingService;
        $this->orderRepository = $orderRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->logger = $logger;
    }

    public static function getHandledMessages(): iterable
    {
        return [SyncPaidOrdersTask::class];
    }

    public function run(): void
    {
        $context = Context::createDefaultContext();
        if (!$this->syncService->getConfigService()->get("syncOrders")) {
            $this->logger->info("[InfoPlus] Cronjbo orders sync is disabled, skipping task.");
            return;
        }
        $syncedOrders = $this->idMappingService->getPendingShipmentOrders($context);
        $this->processOrders($syncedOrders, $context);
    }

    private function processOrders(array $syncedOrders, Context $context): void
    {
        if (empty($syncedOrders)) {
            $this->logger->info('[InfoPlus] No synced orders to process');
            return;
        }
        //get $syncedOrders as array of order numbers
        $orderNos = array_map(function ($order) {
            return $order->getInfoplusId();
        }, $syncedOrders);
        // Query InfoPlus API for orders with orderNo IN ($orderNos)
        $query = ['filter' => 'orderNo in (' . implode(',', $orderNos) . ')'];
        $infoPlusOrders = $this->syncService->getApiClient()->searchOrders($query);

        if (is_string($infoPlusOrders)) {
            $this->logger->error('[InfoPlus] Failed to fetch orders from InfoPlus', ['error' => $infoPlusOrders]);
            return;
        }

        foreach ($infoPlusOrders as $infoPlusOrder) {
            if (!isset($infoPlusOrder['orderNo']) || !isset($infoPlusOrder['status'])) {
                $this->logger->warning('[InfoPlus] Invalid order data from InfoPlus', ['order' => $infoPlusOrder]);
                continue;
            }

            $orderNo = $infoPlusOrder['orderNo'];
            $shopwareOrderId = null;
            foreach ($syncedOrders as $syncedOrder) {
                if ($syncedOrder->getInfoplusId() == $orderNo) {
                    $shopwareOrderId = $syncedOrder->getShopwareOrderId();
                    break;
                }
            }

            if (!$shopwareOrderId) {
                $this->logger->warning('[InfoPlus] No Shopware order found for InfoPlus orderNo', ['orderNo' => $orderNo]);
                continue;
            }
            //infoplus order statuses: Pending, Error, On Order, Processed, Shipped, Back Order, Cancelled or Unknown
            $status = strtolower($infoPlusOrder['status']);
            switch ($status) {
                case 'shipped':
                    $this->processOrder($shopwareOrderId, $infoPlusOrder, $context, 'order_delivery', 'ship', 'shipped');
                    break;
                case 'cancelled':
                    $this->processOrder($shopwareOrderId,$infoPlusOrder, $context, 'order_transaction', 'cancel', 'cancelled');
                    $this->processOrder($shopwareOrderId, $infoPlusOrder, $context, 'order_delivery', 'cancel', 'cancelled');
                    break;
                case 'pending':
                case 'unknown':
                case 'on order':
                case 'processed':
                case 'back order':
//                    $this->logger->info('[InfoPlus] Order status no update needed', ['orderId' => $shopwareOrderId, 'status' => $status]);
                    break;
                case 'error':
                    $this->processOrder($shopwareOrderId, $infoPlusOrder, $context,  'order_transaction', 'fail', 'failed');
                    break;
                default:
                    $this->logger->warning('[InfoPlus] Unhandled InfoPlus status', ['orderId' => $shopwareOrderId, 'status' => $status]);
            }
        }
    }

    private function processOrder(string $shopwareOrderId, $infoPlusOrder,  Context $context, string $entity, string $action, string $targetState): void
    {
        // Load the order with necessary associations
        $criteria = new Criteria([$shopwareOrderId]);
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('deliveries');
        $order = $this->orderRepository->search($criteria, $context)->first();

        if (!$order instanceof OrderEntity) {
            $this->logger->warning('[InfoPlus] Shopware order not found', ['orderId' => $shopwareOrderId]);
            return;
        }

        $entityId = null;
        if ($entity === 'order') {
            $entityId = $shopwareOrderId;
        } elseif ($entity === 'order_delivery') {
            $delivery = $order->getDeliveries()->first();
            if (!$delivery) {
                $this->logger->warning('[InfoPlus] No delivery found for order', ['orderId' => $shopwareOrderId]);
                return;
            }
            $entityId = $delivery->getId();
        } elseif ($entity === 'order_transaction') {
            $transaction = $order->getTransactions()->first();
            if (!$transaction) {
                $this->logger->warning('[InfoPlus] No transaction found for order', ['orderId' => $shopwareOrderId]);
                return;
            }
            $entityId = $transaction->getId();
        }

        try {
            // Perform state transition
            $transition = new Transition(
                $entity,
                $entityId,
                $action,
                'stateId'
            );

            $this->stateMachineRegistry->transition($transition, $context);
            $this->idMappingService->setShippedStatus($shopwareOrderId, (int)$infoPlusOrder['orderNo'], $context);
            $this->logger->info("[InfoPlus] Order updated to $targetState in Shopware", [
                'orderId' => $shopwareOrderId,
                'entity' => $entity
            ]);
        } catch (\Exception $e) {
            $this->logger->error("[InfoPlus] Failed to update order to $targetState in Shopware", [
                'orderId' => $shopwareOrderId,
                'entity' => $entity,
                'error' => $e->getMessage()
            ]);
        }
    }
}