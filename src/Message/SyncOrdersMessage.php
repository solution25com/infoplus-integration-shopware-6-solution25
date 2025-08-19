<?php

namespace InfoPlusCommerce\Message;

use Shopware\Core\Framework\Context;

class SyncOrdersMessage
{
    public function __construct(
        private readonly Context $context,
        private readonly array $orderIds
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrderIds(): array
    {
        return $this->orderIds;
    }
}