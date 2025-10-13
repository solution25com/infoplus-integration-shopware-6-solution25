<?php

declare(strict_types=1);

namespace InfoPlusCommerce\Service;

use InfoPlusCommerce\Client\InfoplusApiClient;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\Process\Process;

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

    /**
     * @param array<int|string>|null $ids
     * @return array<mixed>
     */
    public function syncCategories(Context $context, ?array $ids = null): array
    {
        return $this->categorySyncService->syncCategories($context, $ids);
    }

    /**
     * @param array<int|string>|null $ids
     * @return array<mixed>
     */
    public function syncProducts(Context $context, ?array $ids = null): array
    {
        return $this->productSyncService->syncProducts($context, $ids);
    }

    /**
     * @param array<int|string>|null $ids
     * @return array<mixed>
     */
    public function syncCustomers(Context $context, ?array $ids = null): array
    {
        return $this->customerSyncService->syncCustomers($context, $ids);
    }

    /**
     * @param array<int|string> $orderIds
     * @return array<mixed>
     */
    public function syncOrders(array $orderIds, Context $context): array
    {
        return $this->orderSyncService->syncOrders($orderIds, $context);
    }

    /**
     * @param array<int|string>|null $ids
     * @return array<mixed>
     */
    public function syncInventory(Context $context, ?array $ids = null): array
    {
        return $this->inventorySyncService->syncInventory($context, $ids);
    }

    /**
     * @return array<mixed>
     */
    public function syncLobTest(): array
    {
        $this->logger->info('[InfoPlus] Sync LOB test triggered');
        return $this->infoplusApiClient->getLineOfBusiness();
    }

    /**
     * @return array<mixed>
     */
    public function syncWarehouses(): array
    {
        $this->logger->info('[InfoPlus] Sync warehouses triggered');
        return $this->infoplusApiClient->getWarehouses();
    }

    /**
     * @return array<mixed>
     */
    public function syncCarriers(): array
    {
        $this->logger->info('[InfoPlus] Sync carriers triggered');
        return $this->infoplusApiClient->getCarriers();
    }

    /**
     * @return array<mixed>|string
     */
    public function getItemCategories(): array|string
    {
        return $this->infoplusApiClient->getItemCategories();
    }

    /**
     * @return array<mixed>|string
     */
    public function getItemSubCategories(): array|string
    {
        return $this->infoplusApiClient->getItemSubCategories();
    }

    /**
     * @param array<string,mixed> $query
     * @return array<mixed>
     */
    public function getItems(array $query = []): array
    {
        return $this->infoplusApiClient->searchItems($query);
    }

    /**
     * @return array<mixed>
     */
    public function getOrders(): array
    {
        return $this->infoplusApiClient->searchOrders();
    }

    /**
     * @return array<mixed>
     */
    public function getInventories(): array
    {
        return $this->infoplusApiClient->searchInventoryAdjustments();
    }

    /**
     * @return ConfigService
     */
    public function getConfigService(): ConfigService
    {
        return $this->configService;
    }

    /**
     * @return array<mixed>|string
     */
    public function getCustomers(): array|string
    {
        return $this->infoplusApiClient->searchCustomers();
    }

    /**
     * @param int $isSubCategory
     * @return array<mixed>
     */
    public function getAllCategories(int $isSubCategory, Context $context): array
    {
        return $this->categorySyncService->getAllCategories($isSubCategory, $context);
    }

    /**
     * @return array<mixed>
     */
    public function createCategory(string $name, bool $isSubCategory, Context $context): array
    {
        return $this->categorySyncService->createCategory($name, $isSubCategory, $context);
    }

    /**
     * @return array<mixed>|null
     */
    public function getCategoryById(string $id, Context $context): ?array
    {
        return $this->categorySyncService->getCategoryById($id, $context);
    }

    /**
     * @return array<mixed>
     */
    public function updateCategory(string $id, string $name, Context $context): array
    {
        return $this->categorySyncService->updateCategory($id, $name, $context);
    }

    /**
     * @return array<mixed>
     */
    public function deleteCategory(string $id, Context $context): array
    {
        return $this->categorySyncService->deleteCategory($id, $context);
    }

    /**
     * @return array<mixed>|string
     */
    public function deleteItem(int $id): array|string
    {
        return $this->infoplusApiClient->deleteItem($id);
    }

    /**
     * @return array<mixed>|string
     */
    public function deleteCustomer(int $id): array|string
    {
        return $this->infoplusApiClient->deleteCustomer($id);
    }

    /**
     * @return array<mixed>|string
     */
    public function deleteOrder(int $id): array|string
    {
        return $this->infoplusApiClient->deleteOrder($id);
    }

    /**
     * @return array<mixed>
     */
    public function syncPaidOrders(Context $context): array
    {
        return $this->orderSyncService->syncPaidOrders($context);
    }

    /**
     * @return array<mixed>
     */
    public function orderSyncStart(Context $context): array
    {
        return $this->orderSyncService->orderSyncStart($context);
    }

    /**
     * @return array<mixed>
     */
    public function startSync(): array
    {
        $this->logger->info('[InfoPlus] Sync command triggered');
        $path = str_replace('/public', '/bin/console', (string) getcwd());

        $process = new Process([
            'php',
            $path,
            'infoplus:sync'
        ]);
        $process->setTimeout(null);
        $process->disableOutput();

        try {
            $process->start();
            $this->logger->info('[InfoPlus] Sync command started with PID: ' . $process->getPid());
            return ['status' => true, 'message' => 'Sync command started'];
        } catch (\Exception $e) {
            $this->logger->error('[InfoPlus] Failed to start sync command', ['error' => $e->getMessage()]);
            return ['status' => false, 'message' => 'Failed to start sync command: ' . $e->getMessage()];
        }
    }

    public function returnResponseContinueExecution(): void
    {
        ignore_user_abort(true);
        set_time_limit(0);
    }
}
