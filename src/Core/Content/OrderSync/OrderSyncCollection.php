<?php

declare(strict_types=1);

namespace InfoPlusCommerce\Core\Content\OrderSync;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OrderSyncEntity>
 */
class OrderSyncCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OrderSyncEntity::class;
    }
}
