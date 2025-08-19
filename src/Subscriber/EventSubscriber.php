<?php

namespace InfoPlusCommerce\Subscriber;

use InfoPlusCommerce\Service\SyncService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SyncService $syncService,
        private readonly LoggerInterface $logger
    ){}

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten',
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten',
            OrderEvents::ORDER_WRITTEN_EVENT => 'onOrderWritten',
            StateMachineTransitionEvent::class => 'onTransactionStateTransition',
        ];
    }

    public function onProductWritten(EntityWrittenEvent $event): void
    {
        try {
            if ($this->syncService->getConfigService()->get('syncProducts')) {
                $ids = $event->getIds();
                $this->syncService->syncProducts($event->getContext(), $ids);
            }
        }
        catch (\Exception $e) {
            $this->logger->error('Error syncing products: ' . $e->getMessage(), [
                'event' => $event,
                'exception' => $e,
            ]);
        }
    }

    public function onCustomerWritten(EntityWrittenEvent $event): void
    {
        try {
            if ($this->syncService->getConfigService()->get('syncCustomers')) {
                $ids = $event->getIds();
                $this->syncService->syncCustomers($event->getContext(), $ids);
            }
        }
        catch (\Exception $e) {
            $this->logger->error('Error syncing customers: ' . $e->getMessage(), [
                'event' => $event,
                'exception' => $e,
            ]);
        }
    }

    public function onOrderWritten(EntityWrittenEvent $event): void
    {
        try {
            if ($this->syncService->getConfigService()->get('syncOrders')) {
                $orderIds = $event->getIds();
                $this->syncService->syncOrders($orderIds, $event->getContext());
            }
        }
        catch (\Exception $e) {
            $this->logger->error('Error syncing orders: ' . $e->getMessage(), [
                'event' => $event,
                'exception' => $e,
            ]);
        }
    }
    public function onTransactionStateTransition(StateMachineTransitionEvent $event): void
    {
        try {
            $newState = $event->getToPlace()->getTechnicalName();
            if ($newState === OrderTransactionStates::STATE_PAID) {
                $orderId = $event->getEntityId();
                if ($this->syncService->getConfigService()->get('syncOrders')) {
                    $this->syncService->syncOrders([$orderId], $event->getContext());
                }
            }
        }
        catch (\Exception $e) {
            $this->logger->error('Error in transaction state transition: ' . $e->getMessage(), [
                'event' => $event,
                'exception' => $e,
            ]);
        }
    }
}

