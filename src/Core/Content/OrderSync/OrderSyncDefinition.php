<?php

declare(strict_types=1);

namespace InfoPlusCommerce\Core\Content\OrderSync;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UuidField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderSyncDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'infoplus_order_sync';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrderSyncCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrderSyncEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new Required(), new PrimaryKey()),
            (new StringField('shopware_order_id', 'shopwareOrderId', 64))->addFlags(new Required()),
            (new IntField('infoplus_id', 'infoplusId'))->addFlags(new Required()),
            (new DateTimeField('sync_date', 'syncDate'))->addFlags(new Required()),
            new StringField('order_payment_status', 'orderPaymentStatus', 64),
            new StringField('order_shipping_status', 'orderShippingStatus', 64),
            new StringField('order_status', 'orderStatus', 64),
            (new DateTimeField('created_at', 'createdAt'))->addFlags(new Required()),
            (new DateTimeField('updated_at', 'updatedAt'))->addFlags(new Required()),
        ]);
    }
}
