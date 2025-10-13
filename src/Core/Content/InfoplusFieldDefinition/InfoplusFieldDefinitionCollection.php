<?php

namespace InfoPlusCommerce\Core\Content\InfoplusFieldDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<InfoplusFieldDefinitionEntity>
 */
class InfoplusFieldDefinitionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return InfoplusFieldDefinitionEntity::class;
    }
}
