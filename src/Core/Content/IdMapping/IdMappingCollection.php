<?php

declare(strict_types=1);

namespace InfoPlusCommerce\Core\Content\IdMapping;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<IdMappingEntity>
 */
class IdMappingCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return IdMappingEntity::class;
    }
}
