<?php

namespace InfoPlusCommerce\Subscriber;

use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InfoplusOrderPlacedSubscriber implements EventSubscriberInterface
{
    private EntityRepository $orderLineItemRepository;

    public function __construct(EntityRepository $orderLineItemRepository)
    {
        $this->orderLineItemRepository = $orderLineItemRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $order = $event->getOrder();
        $context = $event->getContext();
        $lineItems = $order->getLineItems();
        if (!$lineItems || $lineItems->count() === 0) {
            return;
        }

        $updates = [];
        foreach ($lineItems as $lineItem) {
            $payload = $lineItem->getPayload() ?? [];
            $payloadCustomFields = $payload['infoplus_customfields'] ?? null;
            if (!\is_array($payloadCustomFields) || empty($payloadCustomFields)) {
                continue;
            }
            // merge into existing customFields
            $current = $lineItem->getCustomFields() ?? [];
            $merged = array_merge($current, $payloadCustomFields);
            $updates[] = [
                'id' => $lineItem->getId(),
                'customFields' => $merged,
            ];
        }

        if (empty($updates)) {
            return;
        }

        $this->orderLineItemRepository->update($updates, $context);
    }
}

