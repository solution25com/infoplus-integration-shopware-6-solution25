<?php

namespace InfoPlusCommerce\Core\Content\InfoplusFieldDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class InfoplusFieldDefinitionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return InfoplusFieldDefinitionEntity::class;
    }
}
