<?php

declare(strict_types=1);

namespace InfoPlusCommerce\Service;

use InfoPlusCommerce\Client\InfoplusApiClient;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;

class SyncService
{
    private CategorySyncService $categorySyncService;
    private ProductSyncService $productSyncService;
    private CustomerSyncService $customerSyncService;
    private OrderSyncService $orderSyncService;
    private InventorySyncService $inventorySyncService;
    private InfoplusApiClient $infoplusApiClient;
    private ConfigService $configService;
    private LoggerInterface $logger;


    public function __construct(
        CategorySyncService $categorySyncService,
        ProductSyncService $productSyncService,
        CustomerSyncService $customerSyncService,
        OrderSyncService $orderSyncService,
        InventorySyncService $inventorySyncService,
        InfoplusApiClient $infoplusApiClient,
        ConfigService $configService,
        LoggerInterface $logger,
    ) {
        $this->categorySyncService = $categorySyncService;
        $this->productSyncService = $productSyncService;
        $this->customerSyncService = $customerSyncService;
        $this->orderSyncService = $orderSyncService;
        $this->inventorySyncService = $inventorySyncService;
        $this->infoplusApiClient = $infoplusApiClient;
        $this->configService = $configService;
        $this->logger = $logger;
    }

    public function syncCategories(Context $context, ?array $ids = null): array
    {
        return $this->categorySyncService->syncCategories($context, $ids);
    }

    public function syncProducts(Context $context, ?array $ids = null): array
    {
        return $this->productSyncService->syncProducts($context, $ids);
    }

    public function syncCustomers(Context $context, ?array $ids = null): array
    {
        return $this->customerSyncService->syncCustomers($context, $ids);
    }

    public function syncOrders(array $orderIds, Context $context): array
    {
        return $this->orderSyncService->syncOrders($orderIds, $context);
    }

    public function syncInventory(Context $context, ?array $ids = null): array
    {
        return $this->inventorySyncService->syncInventory($context, $ids);
    }

    public function syncLobTest(): array
    {
        $this->logger->info('[InfoPlus] Sync LOB test triggered');
        return $this->infoplusApiClient->getLineOfBusiness();
    }

    public function syncWarehouses(): array
    {
        $this->logger->info('[InfoPlus] Sync warehouses triggered');
        return $this->infoplusApiClient->getWarehouses();
    }

    public function syncCarriers(): array
    {
        $this->logger->info('[InfoPlus] Sync carriers triggered');
        return $this->infoplusApiClient->getCarriers();
    }

    public function getItemCategories(): array|string
    {
        return $this->infoplusApiClient->getItemCategories();
    }

    public function getItemSubCategories(): array|string
    {
        return $this->infoplusApiClient->getItemSubCategories();
    }

    public function getItems(array $query = []): array
    {
        return $this->infoplusApiClient->searchItems($query);
    }

    public function getOrders(): array
    {
        return $this->infoplusApiClient->searchOrders();
    }

    public function getInventories(): array
    {
        return $this->infoplusApiClient->searchInventoryAdjustments();
    }

    public function getConfigService()
    {
        return $this->configService;
    }

    public function getCustomers()
    {
        return $this->infoplusApiClient->searchCustomers();
    }

    public function getAllCategories(int $isSubCategory, Context $context): array
    {
        return $this->categorySyncService->getAllCategories($isSubCategory, $context);
    }

    public function createCategory(string $name, bool $isSubCategory, Context $context): array
    {
        return $this->categorySyncService->createCategory($name, $isSubCategory, $context);
    }

    public function getCategoryById(string $id, Context $context): ?array
    {
        return $this->categorySyncService->getCategoryById($id, $context);
    }

    public function updateCategory(string $id, string $name, Context $context): array
    {
        return $this->categorySyncService->updateCategory($id, $name, $context);
    }

    public function deleteCategory(string $id, Context $context): array
    {
        return $this->categorySyncService->deleteCategory($id, $context);
    }

    public function deleteItem(int $id): array|string
    {
        return $this->infoplusApiClient->deleteItem($id);
    }

    public function deleteCustomer(int $id): array|string
    {
        return $this->infoplusApiClient->deleteCustomer($id);
    }

    public function deleteOrder(int $id): array|string
    {
        return $this->infoplusApiClient->deleteOrder($id);
    }

    public function syncPaidOrders(Context $context): array
    {
        return $this->orderSyncService->syncPaidOrders($context);
    }

    public function orderSyncStart(Context $context): array
    {
        return $this->orderSyncService->orderSyncStart($context);
    }

    public function startSync(): array
    {
        $this->logger->info('[InfoPlus] Sync command triggered');
        $path = str_replace('/public', '/bin/console', getcwd());
        $cmd = 'php ' . $path . ' infoplus:sync > /tmp/infoplus_sync.log 2>&1 &';
        $output = [];
        // @phpstan-ignore-next-line Calling exec() is forbidden
        exec($cmd, $output, $returnCode);
        if ($returnCode !== 0) {
            $this->logger->error('[InfoPlus] Failed to start sync command', ['command' => $cmd]);
            return ['status' => false, 'message' => 'Failed to start sync command'];
        }
        return ['status' => true, 'message' => 'Sync command started'];
    }

    public function returnResponseContinueExecution(): void
    {
        ignore_user_abort(true);
        // @phpstan-ignore-next-line Calling session_write_close() is forbidden
        session_write_close();
        set_time_limit(0);
    }
}
