<?php

namespace InfoPlusCommerce\Storefront\Subscriber;

use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class InfoplusCartSubscriber implements EventSubscriberInterface
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeLineItemAddedEvent::class => 'onLineItemAdded',
        ];
    }

    public function onLineItemAdded(BeforeLineItemAddedEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }
        $lineItem = $event->getLineItem();
        $infoplusData = [];
        foreach ($request->request->all() as $key => $value) {
            if (strpos($key, 'infoplus_') === 0) {
                $infoplusData[$key] = $value;
            }
        }
        if (!empty($infoplusData)) {
            $lineItem->setPayloadValue('infoplus_customfields', $infoplusData);
            $existingCustomFields = $lineItem->getPayloadValue('customFields') ?? [];
            if (!\is_array($existingCustomFields)) {
                $existingCustomFields = [];
            }
            $lineItem->setPayloadValue('customFields', array_merge($existingCustomFields, $infoplusData));
        }
    }
}
