<?php

declare(strict_types=1);

namespace InfoPlusCommerce\Service;

use InfoPlusCommerce\Core\Content\IdMapping\IdMappingCollection;
use InfoPlusCommerce\Core\Content\IdMapping\IdMappingEntity;
use InfoPlusCommerce\Core\Content\OrderSync\OrderSyncEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MaxResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class IdMappingService
{
    public function __construct(
        private readonly EntityRepository $idMappingRepository,
        private readonly EntityRepository $orderSyncRepository
    ) {
    }

    public function getInfoplusId(string $entityType, string $shopwareId, Context $context): ?int
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('entityType', $entityType),
            new EqualsFilter('shopwareId', $shopwareId)
        );
        /** @var IdMappingEntity|null $entity */
        $entity = $this->idMappingRepository->search($criteria, $context)->first();
        return $entity?->getInfoplusId();
    }

    public function getInfoplusInfo(string $entityType, string $shopwareId, Context $context): ?array
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('entityType', $entityType),
            new EqualsFilter('shopwareId', $shopwareId)
        );
        /** @var IdMappingEntity|null $result */
        $result = $this->idMappingRepository->search($criteria, $context)->first();
        return $result ? [
            'id' => $result->getId(),
            'entity_type' => $result->getEntityType(),
            'shopware_id' => $result->getShopwareId(),
            'infoplus_id' => $result->getInfoplusId(),
            'created_at' => $result->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $result->getUpdatedAt()->format('Y-m-d H:i:s')
        ] : null;
    }

    public function createInfoplusId(string $entityType, string $shopwareId, Context $context, ?int $infoplusId = null): int
    {
        if ($infoplusId === null) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('entityType', $entityType));
            $criteria->addAggregation(new MaxAggregation(
                'max_infoplus_id',
                'infoplusId'
            ));
            $aggregations = $this->idMappingRepository->aggregate($criteria, $context);
            $maxAgg = $aggregations->get('max_infoplus_id');
            $maxId = ($maxAgg instanceof MaxResult) ? $maxAgg->getMax() : null;
            $infoplusId = ($maxId !== null) ? ((int)$maxId + 1) : 1;
        }
        $existing = $this->getInfoplusInfo($entityType, $shopwareId, $context);
        if ($existing) {
            $this->idMappingRepository->update([
                [
                    'id' => $existing['id'],
                    'entityType' => $entityType,
                    'shopwareId' => $shopwareId,
                    'infoplusId' => $infoplusId,
                    'createdAt' => new \DateTime(),
                    'updatedAt' => new \DateTime()
                ]
            ], $context);
        } else {
            $this->idMappingRepository->create([
                [
                    'id' => Uuid::randomHex(),
                    'entityType' => $entityType,
                    'shopwareId' => $shopwareId,
                    'infoplusId' => $infoplusId,
                    'createdAt' => new \DateTime(),
                    'updatedAt' => new \DateTime()
                ]
            ], $context);
        }
        return $infoplusId;
    }

    public function updateInfoplusUpdatedAt(string $entityType, string $shopwareId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('entityType', $entityType),
            new EqualsFilter('shopwareId', $shopwareId)
        );

        /** @var IdMappingEntity|null $idMapping */
        $idMapping = $this->idMappingRepository->search($criteria, $context)->first();
        if ($idMapping) {
            $this->idMappingRepository->update([
                [
                    'id' => $idMapping->getId(),
                    'updatedAt' => new \DateTime()
                ]
            ], $context);
        }
    }

    public function getOrCreateInfoplusId(string $entityType, string $shopwareId, Context $context, ?int $infoplusId = null): int
    {
        $existing = $this->getInfoplusId($entityType, $shopwareId, $context);
        if ($existing !== null) {
            return $existing;
        }
        return $this->createInfoplusId($entityType, $shopwareId, $context, $infoplusId);
    }

    public function createOrderSyncRecord(
        string $shopwareOrderId,
        string $infoplusOrderId,
        ?string $orderStatus,
        ?string $shippingStatus,
        ?string $paymentStatus,
        Context $context
    ): void {
        $this->orderSyncRepository->create([
            [
                'id' => Uuid::randomHex(),
                'shopwareOrderId' => $shopwareOrderId,
                'infoplusId' => (int)$infoplusOrderId,
                'syncDate' => new \DateTime(),
                'orderPaymentStatus' => $paymentStatus,
                'orderShippingStatus' => $shippingStatus,
                'orderStatus' => $orderStatus
            ]
        ], $context);
    }

    public function updateOrderSyncStatus(
        string $shopwareOrderId,
        string $infoplusOrderId,
        ?string $orderStatus,
        ?string $shippingStatus,
        ?string $paymentStatus,
        Context $context
    ): void {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('shopwareOrderId', $shopwareOrderId),
            new EqualsFilter('infoplusId', $infoplusOrderId)
        );

        /** @var OrderSyncEntity|null $orderSync */
        $orderSync = $this->orderSyncRepository->search($criteria, $context)->first();
        if ($orderSync) {
            $this->orderSyncRepository->update([
                [
                    'id' => $orderSync->getId(),
                    'syncDate' => new \DateTime(),
                    'orderPaymentStatus' => $paymentStatus,
                    'orderShippingStatus' => $shippingStatus,
                    'orderStatus' => $orderStatus
                ]
            ], $context);
        }
    }

    public function setShippedStatus(
        string $shopwareOrderId,
        int $infoplusOrderId,
        Context $context
    ): void {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('shopwareOrderId', $shopwareOrderId),
            new EqualsFilter('infoplusId', $infoplusOrderId)
        );

        /** @var OrderSyncEntity|null $orderSync */
        $orderSync = $this->orderSyncRepository->search($criteria, $context)->first();
        if ($orderSync) {
            $this->orderSyncRepository->update([
                [
                    'id' => $orderSync->getId(),
                    'syncDate' => new \DateTime(),
                    'orderShippingStatus' => "shipped",
                ]
            ], $context);
        }
    }

    public function getSyncedOrderId(string $shopwareOrderId, Context $context): ?int
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('shopwareOrderId', $shopwareOrderId));
        /** @var OrderSyncEntity|null $orderSync */
        $orderSync = $this->orderSyncRepository->search($criteria, $context)->first();
        return $orderSync?->getInfoplusId();
    }

    public function getSyncedOrder(string $shopwareOrderId, Context $context): ?array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('shopwareOrderId', $shopwareOrderId));
        /** @var OrderSyncEntity|null $result */
        $result = $this->orderSyncRepository->search($criteria, $context)->first();
        return $result ? [
            'id' => $result->getId(),
            'shopware_order_id' => $result->getShopwareOrderId(),
            'infoplus_id' => $result->getInfoplusId(),
            'sync_date' => $result->getSyncDate()->format('Y-m-d H:i:s'),
            'order_payment_status' => $result->getOrderPaymentStatus(),
            'order_shipping_status' => $result->getOrderShippingStatus(),
            'order_status' => $result->getOrderStatus()
        ] : null;
    }

    public function getOrderSyncRecord(string $shopwareOrderId, Context $context): ?array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('shopwareOrderId', $shopwareOrderId));

        /** @var OrderSyncEntity|null $result */
        $result = $this->orderSyncRepository->search($criteria, $context)->first();
        return $result ? [
            'order_payment_status' => $result->getOrderPaymentStatus(),
            'order_shipping_status' => $result->getOrderShippingStatus(),
            'order_status' => $result->getOrderStatus(),
            'sync_date' => $result->getSyncDate()->format('Y-m-d H:i:s'),
        ] : null;
    }

    /**
     * @return array<int, mixed>
     */
    public function getPendingShipmentOrders(Context $context, ?string $id = null): array
    {
        $criteria = new Criteria();
        if ($id !== null) {
            $criteria->addFilter(new EqualsFilter('shopwareOrderId', $id));
        }
        $criteria->addFilter(
            new EqualsFilter('orderShippingStatus', 'open'),
            new EqualsFilter('orderPaymentStatus', 'paid')
        );

        return $this->orderSyncRepository->search($criteria, $context)->getElements();
    }

    public function deleteInfoplusId(string $entityType, string $shopwareId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('entityType', $entityType),
            new EqualsFilter('shopwareId', $shopwareId)
        );

        /** @var OrderSyncEntity|null $orderSync */
        $orderSync = $this->orderSyncRepository->search($criteria, $context)->first();
        if ($orderSync) {
            $this->orderSyncRepository->delete([[ 'id' => $orderSync->getId() ]], $context);
        }
    }

    public function deleteIdMapping(string $entityType, string $shopwareId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('entityType', $entityType),
            new EqualsFilter('shopwareId', $shopwareId)
        );

        /** @var IdMappingEntity|null $idMapping */
        $idMapping = $this->idMappingRepository->search($criteria, $context)->first();
        if ($idMapping) {
            $this->idMappingRepository->delete([[ 'id' => $idMapping->getId() ]], $context);
        }
    }

    /**
     * Batch fetch infoplus IDs for a set of products.
     * @param string $entityType
     * @param array $shopwareIds
     * @param Context $context
     * @return array shopwareId => infoplusId
     */
    public function getInfoplusIdsForProducts(string $entityType, array $shopwareIds, Context $context): array
    {
        if (empty($shopwareIds)) {
            return [];
        }
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('entityType', $entityType));
        $criteria->addFilter(new OrFilter(array_map(fn($id) => new EqualsFilter('shopwareId', $id), $shopwareIds)));
        /** @var IdMappingCollection|IdMappingEntity[] $entities */
        $entities = $this->idMappingRepository->search($criteria, $context)->getEntities();
        $result = [];
        foreach ($entities as $entity) {
            $result[$entity->getShopwareId()] = $entity->getInfoplusId();
        }
        return $result;
    }
}
