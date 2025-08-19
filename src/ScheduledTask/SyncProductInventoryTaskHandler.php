<?php declare(strict_types=1);

namespace InfoPlusCommerce\ScheduledTask;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use InfoPlusCommerce\Service\SyncService;
use InfoPlusCommerce\Service\IdMappingService;
use Psr\Log\LoggerInterface;

class SyncProductInventoryTaskHandler extends ScheduledTaskHandler
{
    private SyncService $syncService;
    private IdMappingService $idMappingService;
    private EntityRepository $productRepository;
    private LoggerInterface $logger;

    public function __construct(
        EntityRepository $scheduledTaskRepository,
        SyncService $syncService,
        IdMappingService $idMappingService,
        EntityRepository $productRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->syncService = $syncService;
        $this->idMappingService = $idMappingService;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    public static function getHandledMessages(): iterable
    {
        return [SyncProductInventoryTask::class];
    }

    public function run(): void
    {
        $context = Context::createDefaultContext();
        if (!$this->syncService->getConfigService()->get("syncInventory")) {
            $this->logger->info("[InfoPlus] Inventory sync is disabled, skipping task.");
            return;
        }
        $products = $this->productRepository->search(new Criteria(), $context);
        if ($products->count() === 0) {
            $this->logger->info("[InfoPlus] No active products found, skipping inventory sync.");
            return;
        }
        $skus = [];
        foreach ($products as $product) {
            if ($product->getProductNumber()) {
                $skus[] = $product->getProductNumber();
            }
        }
        $query = ['filter' => 'sku in (\'' . implode('\',\'', $skus) . '\')'];
        $infoPlusInventory = $this->syncService->getItems($query);

        foreach ($infoPlusInventory as $infoPlusItem) {
            $this->processProduct($infoPlusItem, $context);
        }
    }

    private function processProduct(array $infoPlusItem, Context $context): void
    {
        $infoPlusProductId = $infoPlusItem['id'] ?? null;
        $newStock = $infoPlusItem['availableQuantity'] ?? 0;

        if (!$infoPlusProductId) {
            $this->logger->warning("Invalid InfoPlus product data: ID missing");
            return;
        }
        //get product id by $infoPlusItem['sku']
        if (!isset($infoPlusItem['sku'])) {
            $this->logger->warning("Invalid InfoPlus product data: SKU missing for product ID {$infoPlusProductId}");
            return;
        }
        $infoPlusProductSku = $infoPlusItem['sku'];

        try {
            $criteria = new Criteria();
            $criteria->addAssociation('stock');
            $criteria->addFilter(new EqualsFilter('productNumber', $infoPlusProductSku));
            $product = $this->productRepository->search($criteria, $context)->first();

            if (!$product) {
                $this->logger->warning("No Shopware product found with SKU {$infoPlusProductSku}");
                return;
            }

            if ($product->getStock() !== $newStock) {
                $this->productRepository->update([
                    [
                        'id' => $product->getId(),
                        'stock' => $newStock,
                    ],
                ], $context);
                $this->logger->info("Updated stock for product {$product->getId()} to {$newStock}");
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to update product SKU: {$infoPlusProductSku}: {$e->getMessage()}");
        }
    }
}