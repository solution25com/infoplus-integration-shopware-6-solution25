<?php declare(strict_types=1);

namespace InfoPlusCommerce\Core\Content\InfoplusCategory;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class InfoplusCategoryDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'infoplus_category';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return InfoplusCategoryCollection::class;
    }

    public function getEntityClass(): string
    {
        return InfoplusCategoryEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new Required(), new PrimaryKey()),
            (new StringField('id_for_infoplus', 'idForInfoplus', 255))->addFlags(new ApiAware(), new Required()),
            (new IntField('internal_id', 'internalId'))->addFlags(new ApiAware(), new Required()),
            (new StringField('name', 'name', 255))->addFlags(new ApiAware(), new Required()),
            (new BoolField('is_sub_category', 'isSubCategory'))->addFlags(new ApiAware(), new Required()),
            (new CreatedAtField())->addFlags(new ApiAware(), new Required()),
            (new UpdatedAtField())->addFlags(new ApiAware()),
        ]);
    }
}