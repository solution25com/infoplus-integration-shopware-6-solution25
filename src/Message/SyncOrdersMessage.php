<?php

namespace InfoPlusCommerce\Message;

class SyncOrdersMessage
{
    /** @var array<int, string> */
    private array $orderIds;

    /**
     * @param array<int, string> $orderIds
     */
    public function __construct(array $orderIds)
    {
        $this->orderIds = $orderIds;
    }

    /**
     * @return array<int, string>
     */
    public function getOrderIds(): array
    {
        return $this->orderIds;
    }
}
