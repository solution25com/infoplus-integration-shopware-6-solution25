<?php

declare(strict_types=1);

namespace InfoPlusCommerce\Core\Content\InfoplusCategory;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class InfoplusCategoryCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return InfoplusCategoryEntity::class;
    }
}
