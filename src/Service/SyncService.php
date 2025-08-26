<?php declare(strict_types=1);

namespace InfoPlusCommerce\Service;

use InfoPlusCommerce\Client\InfoplusApiClient;
use InfoPlusCommerce\Message\SyncOrdersMessage;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SyncService
{
    public function __construct(
        private readonly ConfigService     $configService,
        private readonly InfoplusApiClient $infoplusApiClient,
        private readonly LoggerInterface   $logger,
        private readonly EntityRepository  $productRepository,
        private readonly EntityRepository  $infoplusCategoryRepository,
        private readonly EntityRepository  $customerRepository,
        private readonly EntityRepository  $orderRepository,
        private readonly IdMappingService  $idMappingService,
        private readonly TranslatorInterface $translator,
        private readonly StateMachineRegistry $stateMachineRegistry,
    )
    {
    }

    public function syncCategories(Context $context, ?array $ids = null): array
    {
        if (!$this->configService->get('syncCategories')) {
            $this->logger->info('[InfoPlus] Category sync is disabled in configuration');
            return ['status' => 'error', 'error' => $this->translator->trans('infoplus.service.errors.categorySyncDisabled')];
        }
        $this->logger->info('[InfoPlus] Sync categories triggered', ['ids' => $ids ?? 'all']);
        $criteria = new Criteria($ids);
        $categories = $this->infoplusCategoryRepository->search($criteria, $context)->getEntities();

        $itemCategories = $this->infoplusApiClient->getItemCategories();
        $itemSubCategories = $this->infoplusApiClient->getItemSubCategories();
        //get existing categories from InfoPlus if not already fetched
        if ($categories->count() === 0) {
            $categoriesToCreate = array_map(function ($category) {
                $category['idForInfoplus'] = $category['id'] ?? null;
                unset($category['id']);
                unset($category['lobId']);
                unset($category['customFields']);
                $category['id'] = Uuid::randomHex();
                $category['isSubCategory'] = false;
                return $category;
            }, $itemCategories);
            if (count($categoriesToCreate) > 0) {
                $this->infoplusCategoryRepository->create($categoriesToCreate, $context);
            }
            $subCategoriesToCreate = array_map(function ($subCategory) {
                $subCategory['idForInfoplus'] = $subCategory['id'] ?? null;
                unset($subCategory['id']);
                unset($subCategory['lobId']);
                unset($subCategory['customFields']);
                $subCategory['id'] = Uuid::randomHex();
                $subCategory['isSubCategory'] = true;
                return $subCategory;
            }, $itemSubCategories);
            if (count($subCategoriesToCreate) > 0) {
                $this->infoplusCategoryRepository->create($subCategoriesToCreate, $context);
            }
            // Refresh categories after creation
            $categories = $this->infoplusCategoryRepository->search($criteria, $context)->getEntities();
        }
        if ($categories->count() === 0) {
            $this->logger->warning('[InfoPlus] No categories found for sync', ['ids' => $ids ?? 'all']);
            return ['status' => 'error', 'error' => $this->translator->trans('infoplus.service.errors.noCategoriesFound')];
        }
        $lobId = $this->configService->get('lobId');
        $results = [];
        foreach ($categories as $category) {
            try {
                $entityType = $category->isSubCategory() ? 'itemSubCategory' : 'itemCategory';
                $data = [
                    'lobId' => $lobId,
                    'id' => $category->getIdForInfoplus(),
                    'name' => $category->getName() ?: ($entityType === 'itemCategory' ? 'Default Category' : 'Default Subcategory'),
                ];

                $data['internalId'] = $existingEntity['internalId'] ?? null;
                $method = $entityType === 'itemCategory' ? 'updateItemCategory' : 'updateItemSubCategory';

                $result = $this->infoplusApiClient->$method($data);
                if (is_array($result) && isset($result['internalId'])) {
                    unset($data['lobId']);
                    $data['internalId'] = (int)$result['internalId'];
                    $data['id'] = $category->getIdForInfoplus();
                    $data['idForInfoplus'] = $category->getIdForInfoplus();
                    $data['isSubCategory'] = $category->isSubCategory();
                    $this->infoplusCategoryRepository->update([$data], $context);
                }

                $results[] = [
                    'id' => $data['id'],
                    'name' => $data['name'],
                    'type' => $entityType,
                    'success' => is_array($result),
                    'error' => is_string($result) ? $result : null
                ];
                if (is_string($result)) {
                    $this->logger->error('[InfoPlus] Failed to update ' . $entityType, [
                        'name' => $data['name'],
                        'id' => $data['id'] ?? 'N/A',
                        'internalId' => $data['internalId'] ?? 'N/A',
                        'data' => $data,
                        'error' => $result
                    ]);
                }
            }
            catch (\Exception $e) {
                $this->logger->error('[InfoPlus] Exception during category sync', [
                    'name' => $category->getName(),
                    'id' => $category->getId(),
                    'isSubCategory' => $category->isSubCategory(),
                    'error' => $e->getMessage()
                ]);
                $results[] = [
                    'name' => $category->getName(),
                    'type' => $category->isSubCategory() ? 'itemSubCategory' : 'itemCategory',
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    public function getAllCategories(int $isSubCategory, Context $context): array
    {

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isSubCategory', $isSubCategory));
        $categories = $this->infoplusCategoryRepository->search($criteria, $context)->getEntities();
        $result = [];
        foreach ($categories as $category) {
            $result[] = [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'internalId' => $category->getInternalId(),
                'idForInfoplus' => $category->getIdForInfoplus(),
                'isSubCategory' => $category->isSubCategory()
            ];
        }
        return $result;
    }

    public function createCategory(string $name, bool $isSubCategory, Context $context): array
    {
        $this->logger->info('[InfoPlus] Creating category', ['name' => $name, 'isSubCategory' => $isSubCategory]);

        $id = Uuid::randomHex();
        $itemCategories = $isSubCategory ?
            $this->infoplusApiClient->getItemSubCategories() :
            $this->infoplusApiClient->getItemCategories();

        $categoryData = [
            'id' => $id,
            'name' => $name,
            'isSubCategory' => $isSubCategory,
            'idForInfoplus' => (string)$this->getMaxCategoryId($itemCategories),
        ];
        // Sync with InfoPlus if enabled
        if ($this->configService->get('syncCategories')) {
            $lobId = $this->configService->get('lobId');
            $infoplusData = [
                'id' => $categoryData['idForInfoplus'],
                'lobId' => $lobId,
                'name' => $name,
            ];

            $method = $isSubCategory ? 'createItemSubCategory' : 'createItemCategory';
            $result = $this->infoplusApiClient->$method($infoplusData);

            if (is_array($result) && isset($result['id'])) {
                $categoryData['internalId'] = (int)$result['internalId'];
                $this->infoplusCategoryRepository->create([$categoryData], $context);
                return [ "success" => true, "message" => $this->translator->trans('infoplus.api.status.categoryCreated') ];
            } else {
                $this->logger->error('[InfoPlus] Failed to create category in InfoPlus', [
                    'name' => $name,
                    'isSubCategory' => $isSubCategory,
                    'error' => is_string($result) ? $result : 'Unknown error',
                ]);
                return [ "success" => false, "message" => is_string($result) ? $result : 'Unknown error' ];
            }
        }
        return [ "success" => false, "message" => $this->translator->trans('infoplus.service.errors.categorySyncDisabled') ];
    }

    public function getCategoryById(string $id, Context $context): ?array
    {
        $this->logger->info('[InfoPlus] Fetching category by ID', ['id' => $id]);

        $criteria = new Criteria([$id]);
        $category = $this->infoplusCategoryRepository->search($criteria, $context)->first();

        if (!$category) {
            $this->logger->warning('[InfoPlus] Category not found', ['id' => $id]);
            return null;
        }

        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'internalId' => $category->getInternalId(),
            'idForInfoplus' => $category->getIdForInfoplus(),
            'isSubCategory' => $category->isSubCategory(),
        ];
    }

    public function updateCategory(string $id, string $name, Context $context): array
    {
        $this->logger->info('[InfoPlus] Updating category', ['id' => $id, 'name' => $name]);

        $criteria = new Criteria([$id]);
        $category = $this->infoplusCategoryRepository->search($criteria, $context)->first();

        if (!$category) {
            $this->logger->error('[InfoPlus] Category not found for update', ['id' => $id]);
            throw new \Exception('Category not found');
        }

        $categoryData = [
            'id' => $id,
            'name' => $name,
            'isSubCategory' => $category->isSubCategory(),
            'internalId' => $category->getInternalId(),
            'idForInfoplus' => $category->getIdForInfoplus(),
        ];
        // Sync with InfoPlus if enabled
        if ($this->configService->get('syncCategories') && $categoryData['idForInfoplus'] != null) {
            $lobId = $this->configService->get('lobId');
            $infoplusData = [
                'lobId' => $lobId,
                'id' => $categoryData['idForInfoplus'],
                'name' => $name,
                'internalId' => $categoryData['internalId'],
            ];

            $method = $category->isSubCategory() ? 'updateItemSubCategory' : 'updateItemCategory';
            $result = $this->infoplusApiClient->$method($infoplusData);

            if (is_string($result)) {
                $this->logger->error('[InfoPlus] Failed to update category in InfoPlus', [
                    'id' => $id,
                    'name' => $name,
                    'isSubCategory' => $category->isSubCategory(),
                    'error' => $result,
                ]);
                return [ "success" => false, "message" => $result ];
            } else {
                // Update category locally
                $this->infoplusCategoryRepository->update([$categoryData], $context);
                return [ "success" => true, "message" => $this->translator->trans('infoplus.api.status.categoryUpdated') ];
            }
        }

        return [ "success" => false, "message" => $this->translator->trans('infoplus.service.errors.categorySyncDisabled') ];
    }

    public function deleteCategory(string $id, Context $context): array
    {
        $this->logger->info('[InfoPlus] Delete category triggered', ['id' => $id]);

        // Fetch category to get idForInfoplus and isSubCategory
        $category = $this->infoplusCategoryRepository->search(new Criteria([$id]), $context)->first();
        if (!$category) {
            $this->logger->error('[InfoPlus] Category not found for deletion', ['id' => $id]);
            return [ "success" => false, "message" => $this->translator->trans('infoplus.api.errors.categoryNotFound') ];
        }

        // Delete from InfoPlus if sync is enabled and idForInfoplus exists
        if ($this->configService->get('syncCategories') && $category->getInternalId()) {
            $method = $category->isSubCategory() ? 'deleteItemSubCategory' : 'deleteItemCategory';
            $result = $this->infoplusApiClient->$method($category->getInternalId());
                $this->infoplusCategoryRepository->delete([['id' => $id]], $context);
                return [ "success" => true, "message" => $this->translator->trans('infoplus.api.status.categoryDeleted') ];
        }
        return [ "success" => false, "message" => $this->translator->trans('infoplus.service.errors.categorySyncDisabled') ];
    }

    private function getMaxCategoryId(array $categories): int
    {
        $maxId = 0;
        foreach ($categories as $category) {
            if (isset($category['id']) && is_numeric($category['id'])) {
                $maxId = max($maxId, (int)$category['id']);
            }
        }
        return $maxId + 1;
    }

    public function syncProducts(Context $context, ?array $ids = null): array
    {
        if (!($this->configService->get('syncOrders') || $this->configService->get('syncProducts'))) {
            $this->logger->info('[InfoPlus] Product sync is disabled in configuration');
            return ['status' => 'error', 'error' => $this->translator->trans('infoplus.service.errors.productSyncDisabled')];
        }
        $this->logger->info('[InfoPlus] Sync products triggered', ['ids' => $ids ?? 'all']);
        $criteria = new Criteria($ids);
        $criteria->addAssociation('price');
        $products = $this->productRepository->search($criteria, $context)->getEntities();

        if ($products->count() === 0) {
            $this->logger->warning('[InfoPlus] No products found for sync', ['ids' => $ids ?? 'all']);
            return ['status' => 'error', 'error' => 'infoplus.service.errors.noProductsFound'];
        }

        $lobId = $this->configService->get('lobId');

        $results = [];
        foreach ($products as $product) {
            $sku = $product->getProductNumber();
            $itemDescription = $product->getName() ?: 'Default Product Description';
            $infoplusId = $this->idMappingService->getInfoplusId('item', $product->getId(), $context);
            $existingItems = $this->infoplusApiClient->getBySKU($lobId, $sku);

            if (is_string($existingItems)) {
                $this->logger->error('[InfoPlus] Failed to fetch item by SKU', ['sku' => $sku, 'error' => $existingItems]);
                $results[] = [
                    'sku' => $sku,
                    'success' => false,
                    'error' => $this->translator->trans('infoplus.service.errors.failedToFetchItemBySku') . ' ' . $existingItems
                ];
                continue;
            }

            if ($existingItems && isset($existingItems['id'])) {
                if ($infoplusId && $infoplusId !== $existingItems['id']) {
                    $this->idMappingService->deleteInfoplusId('item', $product->getId(), $context);
                    $infoplusId = $existingItems['id'];
                    $this->idMappingService->createInfoplusId('item', $product->getId(), $infoplusId, $context);
                } else {
                    $infoplusId = $existingItems['id'];
                }
            }

            $customFields = $product->getCustomFields() ?: [];
            $majorGroupId = $customFields['infoplus_major_group_id'] ?? null;
            $subGroupId = $customFields['infoplus_sub_group_id'] ?? null;
            if (empty($majorGroupId) || empty($subGroupId)) {
                if ($existingItems){
                    $majorGroupId = $existingItems['majorGroupId'] ?? null;
                    $subGroupId = $existingItems['subGroupId'] ?? null;
                    //update product custom fields if they are missing
                    if ($majorGroupId && $subGroupId) {
                        $this->productRepository->update([
                            [
                                'id' => $product->getId(),
                                'customFields' => [
                                    'infoplus_major_group_id' => $majorGroupId,
                                    'infoplus_sub_group_id' => $subGroupId
                                ]
                            ]
                        ], $context);
                    }
                }
                else {
                    $this->logger->error('[InfoPlus] Missing category or subcategory for product', [
                        'sku' => $sku,
                        'majorGroupId' => $majorGroupId,
                        'subGroupId' => $subGroupId
                    ]);
                    $results[] = [
                        'sku' => $sku,
                        'success' => false,
                        'error' => 'Missing category or subcategory for SKU: ' . $sku
                    ];
                    continue;
                }
            }

            $categoryCriteria = new Criteria();
            $categoryCriteria->addFilter(new EqualsFilter('internalId', $majorGroupId));
            $category = $this->infoplusCategoryRepository->search($categoryCriteria, $context)->first();
            if (!$category) {
                $this->logger->error('[InfoPlus] Major group not found in infoplus_category', [
                    'sku' => $sku,
                    'majorGroupId' => $majorGroupId
                ]);
                $results[] = [
                    'sku' => $sku,
                    'success' => false,
                    'error' => 'Major group not found for internalId: ' . $majorGroupId
                ];
                continue;
            }

            $subCategoryCriteria = new Criteria();
            $subCategoryCriteria->addFilter(new EqualsFilter('internalId', $subGroupId));
            $subCategory = $this->infoplusCategoryRepository->search($subCategoryCriteria, $context)->first();
            if (!$subCategory) {
                $this->logger->error('[InfoPlus] Subgroup not found in infoplus_category', [
                    'sku' => $sku,
                    'subGroupId' => $subGroupId
                ]);
                $results[] = [
                    'sku' => $sku,
                    'success' => false,
                    'error' => 'Subgroup not found for internalId: ' . $subGroupId
                ];
                continue;
            }

            $receivingCriteriaSchemeId = 1;
            $data = [
                'id' => $infoplusId,
                'lobId' => $lobId,
                'sku' => $sku,
                'itemDescription' => $itemDescription,
                'majorGroupId' => (int)$majorGroupId,
                'subGroupId' => (int)$subGroupId,
                'upc' => $product->getEan() ?? '',
                'length' => $product->getLength() ?? 1.0,
                'width' => $product->getWidth() ?? 1.0,
                'height' => $product->getHeight() ?? 1.0,
                'backorder' => 'Yes',
                'chargeCode' => 'Not Chargeable',
                'status' => 'Active',
                'seasonalItem' => 'No',
                'secure' => 'No',
                'unitCode' => 'PKG',
                'forwardLotMixingRule' => 'SKU',
                'storageLotMixingRule' => 'SKU',
                'forwardItemMixingRule' => 'Single',
                'storageItemMixingRule' => 'Single',
                'allocationRule' => 'Labor Optimized',
                'maxCycle' => 0,
                'maxInterim' => 0,
                'receivingCriteriaSchemeId' => $receivingCriteriaSchemeId,
                'hazmat' => 'No',
                'customFields' => (object)[]
            ];

            try {
                if ($infoplusId && !empty($existingItems)) {
                    $result = $this->infoplusApiClient->updateItem($data);
                    if (is_array($result) && isset($result['id'])) {
                        $this->idMappingService->updateInfoplusUpdatedAt('item', $product->getId(), $context);
                        $results[] = [
                            'sku' => $sku,
                            'success' => true,
                            'message' => 'Product updated successfully',
                            'inventrorySync' => $this->syncInventory($context, [$product->getId()])
                        ];
                    } else {
                        $this->logger->error('[InfoPlus] Failed to update product', [
                            'sku' => $sku,
                            'error' => is_string($result) ? $result : 'Unknown error'
                        ]);
                        $results[] = [
                            'sku' => $sku,
                            'success' => false,
                            'error' => is_string($result) ? $result : 'Unknown error during update'
                        ];
                    }
                } else {
                    unset($data['id']);
                    $result = $this->infoplusApiClient->createItem($data);
                    if (is_array($result) && isset($result['id'])) {
                        $this->idMappingService->createInfoplusId('item', $product->getId(), $result['id'], $context);
                        $results[] = [
                            'sku' => $sku,
                            'success' => true,
                            'message' => 'Product created successfully',
                            'inventrorySync' => $this->syncInventory($context, [$product->getId()])
                        ];
                    } else {
                        $this->logger->error('[InfoPlus] Failed to create product', [
                            'sku' => $sku,
                            'error' => is_string($result) ? $result : 'Unknown error'
                        ]);
                        $results[] = [
                            'sku' => $sku,
                            'success' => false,
                            'error' => is_string($result) ? $result : 'Unknown error during creation'
                        ];
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('[InfoPlus] Exception during product sync', [
                    'sku' => $sku,
                    'error' => $e->getMessage()
                ]);
                $results[] = [
                    'sku' => $sku,
                    'success' => false,
                    'error' => 'Exception: ' . $e->getMessage()
                ];
            }
        }

        $this->logger->info('[InfoPlus] Completed product sync', ['results' => $results]);
        return $results;
    }

    public function syncCustomers(Context $context, ?array $ids = null): array
    {
        if (!($this->configService->get('syncOrder') || $this->configService->get('syncCustomers'))) {
            $this->logger->info('[InfoPlus] Customer sync is disabled in configuration');
            return ['status' => 'error', 'error' => $this->translator->trans('infoplus.service.errors.customerSyncDisabled')];
        }
        $this->logger->info('[InfoPlus] Sync customers triggered', ['ids' => $ids ?? 'all']);
        $criteria = new Criteria($ids);
        $criteria->addAssociation('defaultBillingAddress');
        $criteria->addAssociation('defaultBillingAddress.country');
        $criteria->addAssociation('defaultBillingAddress.countryState');
        $customers = $this->customerRepository->search($criteria, $context)->getEntities();

        if ($customers->count() === 0) {
            $this->logger->warning('[InfoPlus] No customers found for sync', ['ids' => $ids ?? 'all']);
            return ['status' => 'error', 'error' => $this->translator->trans('infoplus.service.errors.noCustomersFound')];
        }

        $lobId = $this->configService->get('lobId');
        $allCarriers = $this->infoplusApiClient->getCarriers(['filter' => "lobId eq $lobId"]);
        if (is_string($allCarriers)) {
            $this->logger->error('[InfoPlus] Failed to fetch carriers', ['error' => $allCarriers]);
            return ['status' => 'error', 'error' => $this->translator->trans('infoplus.service.errors.failedToFetchCarriers')];
        }
        $truckCarriers = array_filter($allCarriers, fn($carrier) => strpos($carrier['label'], 'TRUCK') !== false || $carrier['carrier'] === 100);
        $packageCarriers = array_filter($allCarriers, fn($carrier) => strpos($carrier['label'], 'UPS') !== false || strpos($carrier['label'], 'USPS') !== false);

        $truckCarrierId = !empty($truckCarriers) ? (string)reset($truckCarriers)['carrier'] : null;
        $packageCarrierId = !empty($packageCarriers) ? (string)reset($packageCarriers)['carrier'] : null;

        if (!$truckCarrierId || !$packageCarrierId) {
            $this->logger->warning('[InfoPlus] No truck or package carriers found, using default values (may cause error)');
            $truckCarrierId = $truckCarrierId ?? '100';
            $packageCarrierId = $packageCarrierId ?? '0';
        }

        $results = [];
        foreach ($customers as $customer) {
            $billingAddress = $customer->getDefaultBillingAddress();
            if (!$billingAddress) {
                $this->logger->warning('[InfoPlus] No billing address found for customer ' . $customer->getId());
                continue;
            }
            $data = [
                'lobId' => (string)$lobId,
                'customerNo' => ($customer->getCustomerNumber() ?? $customer->getId()),
                'name' => $customer->getFirstName() . ' ' . $customer->getLastName(),
                'attention' => '',
                'street' => $billingAddress->getStreet(),
                'street2' => $billingAddress->getAdditionalAddressLine1() ?? '',
                'street3Province' => '',
                'city' => $billingAddress->getCity(),
                'zipCode' => $billingAddress->getZipcode(),
                'country' => MappingService::mapIsoToInfoplusCountry($billingAddress->getCountry() ? $billingAddress->getCountry()->getIso() : 'US'),
                'phone' => $customer->getDefaultBillingAddress()->getPhoneNumber() ?? '',
                'email' => $customer->getEmail(),
                'truckCarrierId' => $truckCarrierId,
                'packageCarrierId' => $packageCarrierId,
                'weightBreak' => '0',
                'residential' => 'No',
                'customFields' => null
            ];

            if ($billingAddress->getCountryState() && $data['country'] === 'UNITED STATES') {
                $data['state'] = MappingService::mapIsoToInfoplusUsState($billingAddress->getCountryState()->getShortCode() ?? '');
            }

            $existingCustomer = $this->infoplusApiClient->getCustomerByCustomerNo($lobId, $data['customerNo']);
            if (is_string($existingCustomer)) {
                $this->logger->error('[InfoPlus] Failed to fetch customer by customerNo', ['customerNo' => $data['customerNo'], 'error' => $existingCustomer]);
                $results[] = [
                    'customerNo' => $data['customerNo'],
                    'success' => false,
                    'error' => $existingCustomer
                ];
                continue;
            }
            if ($existingCustomer) {
                $data['id'] = $existingCustomer['id'];
                $result = $this->infoplusApiClient->updateCustomer($data);
                if (is_array($result) && isset($result['id'])) {
                    $this->idMappingService->createInfoplusId('customer', $customer->getId(), $result['id'], $context);
                }
            } else {
                unset($data['id']);
                $result = $this->infoplusApiClient->createCustomer($data);
                if (is_array($result) && isset($result['id'])) {
                    $this->idMappingService->createInfoplusId('customer', $customer->getId(), $result['id'], $context);
                }
            }

            $results[] = [
                'customerNo' => $data['customerNo'],
                'success' => is_array($result),
                'error' => is_string($result) ? $result : null
            ];
            if (is_string($result)) {
                $this->logger->error('[InfoPlus] Failed to sync customer', [
                    'customerNo' => $data['customerNo'],
                    'name' => $data['name'],
                    'truckCarrierId' => $data['truckCarrierId'],
                    'packageCarrierId' => $data['packageCarrierId'],
                    'weightBreak' => $data['weightBreak'],
                    'residential' => $data['residential'],
                    'error' => $result
                ]);
            }
            usleep(500000);
        }
        return $results;
    }

    public function syncInventory(Context $context, ?array $ids = null): array
    {
        if (!($this->configService->get('syncProducts') || $this->configService->get('syncInventory'))) {
            $this->logger->info('[InfoPlus] Inventory sync is disabled in configuration');
            return ['status' => 'error', 'error' => $this->translator->trans('infoplus.service.errors.inventorySyncDisabled')];
        }
        $criteria = new Criteria($ids);
        $products = $this->productRepository->search($criteria, $context);
        if ($products->count() === 0) {
            $this->logger->info("[InfoPlus] No active products found, skipping inventory sync.");
            return ['status' => 'error', 'error' => $this->translator->trans('infoplus.service.errors.noProductsFoundForInventory')];
        }
        $skus = [];
        foreach ($products as $product) {
            if ($product->getProductNumber()) {
                $skus[] = $product->getProductNumber();
            }
        }
        $query = ['filter' => 'sku in (\'' . implode('\',\'', $skus) . '\')'];
        $infoPlusInventory = $this->getItems($query);
        $returnArray = [];
        foreach ($infoPlusInventory as $infoPlusItem) {
            $returnArray[] = $this->processProduct($infoPlusItem, $context);
        }
        return $returnArray;
    }

    private function processProduct(array $infoPlusItem, Context $context): array
    {
        $infoPlusProductId = $infoPlusItem['id'] ?? null;
        $newStock = $infoPlusItem['availableQuantity'] ?? 0;

        if (!$infoPlusProductId) {
            $this->logger->warning("Invalid InfoPlus product data: ID missing");
            return [
                'success' => false,
                'product' => $infoPlusItem['sku'],
                'message' => "No stock change",
                'error' => 'Invalid InfoPlus product data: ID missing'
            ];
        }
        //get product id by $infoPlusItem['sku']
        if (!isset($infoPlusItem['sku'])) {
            $this->logger->warning("Invalid InfoPlus product data: SKU missing for product ID {$infoPlusProductId}");
            return [
                'success' => false,
                'product' => $infoPlusItem['sku'],
                'message' => "No stock change",
                'error' => "Invalid InfoPlus product data: SKU missing for product ID {$infoPlusProductId}"
            ];
        }
        $infoPlusProductSku = $infoPlusItem['sku'];

        try {
            $criteria = new Criteria();
            $criteria->addAssociation('stock');
            $criteria->addFilter(new EqualsFilter('productNumber', $infoPlusProductSku));
            $product = $this->productRepository->search($criteria, $context)->first();

            if (!$product) {
                $this->logger->warning("No Shopware product found with SKU {$infoPlusProductSku}");
                return [
                    'success' => false,
                    'product' => $infoPlusItem['sku'],
                    'message' => "No stock change",
                    'error' => "No Shopware product found with SKU {$infoPlusProductSku}"
                ];
            }
            $currentStock = $product->getStock();
            if ($currentStock !== $newStock) {
                $templateVariables = new ArrayStruct([
                    'source' => 'Infoplus'
                ]);
                $context->addExtension('inventory_sync', $templateVariables);
                $this->productRepository->update([
                    [
                        'id' => $product->getId(),
                        'stock' => $newStock,
                    ],
                ], $context);
                $this->logger->info("Updated stock for product {$product->getId()} to {$newStock}");
                return [
                    'success' => true,
                    'product' => $infoPlusItem['sku'],
                    'message' => "Stock updated from {$currentStock} to {$newStock}",
                    'error' => null
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to update product SKU: {$infoPlusProductSku}: {$e->getMessage()}");
            return [
                'success' => false,
                'product' => $infoPlusItem['sku'],
                'message' => "No stock change",
                'error' => "Failed to update product SKU: {$infoPlusProductSku}: {$e->getMessage()}"
            ];
        }
        return [
            'success' => true,
            'product' => $infoPlusItem['sku'],
            'message' => "No stock change",
            'error' => null
        ];
    }

    public function orderSyncStart(Context $context): array
    {
        if (!$this->configService->get('syncOrders')) {
            $this->logger->info('[InfoPlus] Order sync is disabled in configuration');
            return ['status' => 'error', 'error' => $this->translator->trans('infoplus.service.errors.orderSyncDisabled')];
        }
        $this->logger->info('[InfoPlus] Order sync command triggered via API');

        $criteria = new Criteria();
        $criteria->addAssociation('lineItems.product');
        $criteria->addAssociation('shippingAddress');
        $criteria->addAssociation('orderCustomer');

        $orders = $this->orderRepository->search($criteria, $context);
        $orderIds = array_values($orders->getIds());
        return $this->syncOrders($orderIds, $context);
    }

    public function syncOrders(array $orderIds, Context $context): array
    {
        if (!$this->configService->get('syncOrders')) {
            $this->logger->info('[InfoPlus] Order sync is disabled in configuration');
            return ['status' => 'error', 'error' => $this->translator->trans('infoplus.service.errors.orderSyncDisabled')];
        }
        $this->logger->info('[InfoPlus] Sync orders triggered', ['orderIds' => $orderIds]);
        $criteria = new Criteria($orderIds);
        $criteria->addAssociation('lineItems.product');
        $criteria->addAssociation('shippingAddress');
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('billingAddress.country');
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('deliveries.stateMachineState');
        $criteria->addAssociation('transactions.stateMachineState');

        $criteria->addFilter(
            new EqualsFilter('transactions.stateMachineState.technicalName', 'paid')
        );

        $orders = $this->orderRepository->search($criteria, $context)->getEntities();

        if ($orders->count() === 0) {
            $this->logger->warning('[InfoPlus] No orders found for sync', ['orderIds' => $orderIds]);
            return ['status' => 'error', 'error' => $this->translator->trans('infoplus.service.errors.noOrdersFound')];
        }

        $results = [];
        foreach ($orders as $order) {
            $currentOrderStatus = $order->getStateMachineState() ? $order->getStateMachineState()->getTechnicalName() : null;
            $currentShippingStatus = $order->getDeliveries()->first() && $order->getDeliveries()->first()->getStateMachineState()
                ? $order->getDeliveries()->first()->getStateMachineState()->getTechnicalName()
                : null;
            $currentPaymentStatus = $order->getTransactions()->first() && $order->getTransactions()->first()->getStateMachineState()
                ? $order->getTransactions()->first()->getStateMachineState()->getTechnicalName()
                : null;
            $syncedOrder = $this->idMappingService->getSyncedOrderId($order->getId(), $context);

            $needsSync = false;
            $existingSync = null;
            if ($syncedOrder) {
                $existingSync = $this->idMappingService->getOrderSyncRecord($order->getId(), $context);
                if ($existingSync) {
                    $needsSync = (
                        $existingSync['order_status'] !== $currentOrderStatus ||
                        $existingSync['order_shipping_status'] !== $currentShippingStatus ||
                        $existingSync['order_payment_status'] !== $currentPaymentStatus
                    );
                } else {
                    $needsSync = true;
                }
            } else {
                $needsSync = true;
            }

            if (!$needsSync) {
                $this->logger->info('[InfoPlus] Order sync skipped, no status changes', [
                    'orderId' => $order->getId(),
                    'orderNo' => $order->getOrderNumber(),
                    'currentStatuses' => [
                        'order_status' => $currentOrderStatus,
                        'shipping_status' => $currentShippingStatus,
                        'payment_status' => $currentPaymentStatus
                    ],
                    'existingStatuses' => $existingSync
                ]);
                $results[] = [
                    'orderNo' => $order->getOrderNumber(),
                    'success' => true,
                    'error' => null,
                    'syncOrderShipping' => $this->syncPaidOrders($context, $order->getId())
                ];
                continue;
            }

            $lineItems = [];
            foreach ($order->getLineItems() as $item) {
                if ($item->getProduct() && $item->getProduct()->getProductNumber()) {
                    $lineItems[] = [
                        'sku' => $item->getProduct()->getProductNumber(),
                        'orderedQty' => $item->getQuantity(),
                        'lobId' => $this->configService->get('lobId'),
                        'unitCost' => $item->getPrice()->getUnitPrice() ?? 0
                    ];
                }
            }
            $billingAddress = $order->getBillingAddress();
            $data = [
                'lobId' => $this->configService->get('lobId'),
                'customerOrderNo' => $order->getOrderNumber(),
                'warehouseId' => $this->configService->get('warehouseId'),
                'orderDate' => $order->getOrderDateTime()->format('Y-m-d\TH:i:s\Z'),
                'customerNo' => ($order->getOrderCustomer() ? $order->getOrderCustomer()->getCustomerNumber() : ''),
                'carrierId' => $this->configService->get('carrierId'),
                'lineItems' => $lineItems,
                'billToAttention' => $billingAddress ? ($billingAddress->getFirstName() . ' ' . $billingAddress->getLastName()) : '',
                'billToCompany' => $billingAddress ? ($billingAddress->getCompany() ?? $billingAddress->getFirstName() . ' ' . $billingAddress->getLastName()) : 'no company',
                'billToStreet' => $billingAddress ? $billingAddress->getStreet() : '',
                'billToStreet2' => $billingAddress ? ($billingAddress->getAdditionalAddressLine1() ?? '') : '',
                'billToStreet3' => $billingAddress ? ($billingAddress->getAdditionalAddressLine2() ?? '') : '',
                'billToCity' => $billingAddress ? $billingAddress->getCity() : '',
                'billToState' => $billingAddress && $billingAddress->getCountryState() ? $billingAddress->getCountryState()->getName() : '',
                'billToZip' => $billingAddress ? $billingAddress->getZipcode() : '',
                'billToCountry' => $billingAddress && $billingAddress->getCountry() ? $billingAddress->getCountry()->getIso() : 'US',
                'billToPhone' => $billingAddress ? ($billingAddress->getPhoneNumber() ?? '') : '',
                'billToEmail' => $order->getOrderCustomer() ? $order->getOrderCustomer()->getEmail() : '',
            ];

            $result = null;
            if ($syncedOrder) {
                $data['orderNo'] = $syncedOrder;
                $result = $this->infoplusApiClient->updateOrder($data);
                if (is_array($result) && isset($result['orderNo'])) {
                    $this->idMappingService->updateOrderSyncStatus(
                        $order->getId(),
                        $result['orderNo'],
                        $currentOrderStatus,
                        $currentShippingStatus,
                        $currentPaymentStatus,
                        $context
                    );
                } else {
                    $this->logger->error('[InfoPlus] Failed to update order sync record', ['orderNo' => $order->getOrderNumber(), 'error' => is_string($result) ? $result : 'Unknown error']);
                }
            } else {
                $result = $this->infoplusApiClient->createOrder($data);
                if (is_array($result) && isset($result['orderNo'])) {
                    $this->idMappingService->createOrderSyncRecord(
                        $order->getId(),
                        (string)$result['orderNo'],
                        $currentOrderStatus,
                        $currentShippingStatus,
                        $currentPaymentStatus,
                        $context
                    );
                } else {
                    $this->logger->error('[InfoPlus] Failed to create order sync record', ['orderNo' => $order->getOrderNumber(), 'error' => is_string($result) ? $result : 'Unknown error']);
                }
            }

            $results[] = [
                'orderNo' => $order->getOrderNumber(),
                'success' => is_array($result),
                'error' => is_string($result) ? $result : null,
                'syncOrderShipping' => $this->syncPaidOrders($context, $order->getId())
            ];
        }
        $this->logger->info('[InfoPlus] Completed background order sync', ['results' => $results]);
        return $results;
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

    public function deleteItem(int $id): bool
    {
        $this->logger->info('[InfoPlus] Delete item triggered', ['id' => $id]);
        $result = $this->infoplusApiClient->deleteItem($id);
        if (is_string($result)) {
            $this->logger->error('[InfoPlus] Failed to delete item', ['id' => $id, 'error' => $result]);
            return false;
        }
        return true;
    }

    public function deleteCustomer(int $id): bool
    {
        $this->logger->info('[InfoPlus] Delete customer triggered', ['id' => $id]);
        $result = $this->infoplusApiClient->deleteCustomer($id);
        if (is_string($result)) {
            $this->logger->error('[InfoPlus] Failed to delete customer', ['id' => $id, 'error' => $result]);
            return false;
        }
        return true;
    }

    public function deleteOrder(int $id): bool
    {
        $this->logger->info('[InfoPlus] Delete order triggered', ['id' => $id]);
        $result = $this->infoplusApiClient->deleteOrder($id);
        if (is_string($result)) {
            $this->logger->error('[InfoPlus] Failed to delete order', ['id' => $id, 'error' => $result]);
            return false;
        }
        return true;
    }

    public function getConfigService()
    {
        return $this->configService;
    }

    public function getCustomers()
    {
        return $this->infoplusApiClient->searchCustomers();
    }

    public function getOrderRepository()
    {
        return $this->orderRepository;
    }

    public function getApiClient()
    {
        return $this->infoplusApiClient;
    }

    public function getMappingService()
    {
        return $this->idMappingService;
    }

    public function startSync(): array
    {
        $this->logger->info('[InfoPlus] Sync command triggered');
        $path = str_replace('/public', '/bin/console', getcwd());
        $cmd = 'php ' . $path . ' infoplus:sync > /tmp/infoplus_sync.log 2>&1 &';
        $output = [];
        exec($cmd, $output, $returnCode);
        if ($returnCode !== 0) {
            $this->logger->error('[InfoPlus] Failed to start sync command', ['command' => $cmd]);
            return ['status' => false, 'message' => $this->translator->trans('infoplus.service.errors.failedToStartSyncCommand')];
        }
        return ['status' => true, 'message' => $this->translator->trans('infoplus.api.status.syncCommandStarted')];
    }

    public function returnResponseContinueExecution($time_to_live = 180)
    {
        ignore_user_abort(true);
        session_write_close();
        set_time_limit(0);
    }
    public function syncPaidOrders(Context $context, ?string $id = null): array
    {
        if (!$this->configService->get("syncOrders")) {
            $this->logger->info("[InfoPlus] Cronjob orders sync is disabled, skipping task.");
            return [
                'status' => 'error',
                'error' => $this->translator->trans('infoplus.service.errors.orderSyncDisabled')
            ];
        }

        $syncedOrders = $this->idMappingService->getPendingShipmentOrders($context, $id);
        return $this->processOrders($syncedOrders, $context);
    }

    private function processOrders(array $syncedOrders, Context $context): array
    {
        $returnArray = [];

        if (empty($syncedOrders)) {
            $this->logger->info('[InfoPlus] No synced orders to process');
            return [
                'status' => 'error',
                'error' => $this->translator->trans('infoplus.service.errors.noOrdersFound')
            ];
        }

        // Get $syncedOrders as array of order numbers
        $orderNos = array_map(function ($order) {
            return $order->getInfoplusId();
        }, $syncedOrders);

        // Query InfoPlus API for orders with orderNo IN ($orderNos)
        $query = ['filter' => 'orderNo in (' . implode(',', $orderNos) . ')'];
        $infoPlusOrders = $this->infoplusApiClient->searchOrders($query);

        if (is_string($infoPlusOrders)) {
            $this->logger->error('[InfoPlus] Failed to fetch orders from InfoPlus', ['error' => $infoPlusOrders]);
            return [
                'status' => 'error',
                'error' => $this->translator->trans('infoplus.service.errors.apiFetchFailed', ['error' => $infoPlusOrders])
            ];
        }

        foreach ($infoPlusOrders as $infoPlusOrder) {
            if (!isset($infoPlusOrder['orderNo']) || !isset($infoPlusOrder['status'])) {
                $this->logger->warning('[InfoPlus] Invalid order data from InfoPlus', ['order' => $infoPlusOrder]);
                $returnArray[] = [
                    'success' => false,
                    'orderNo' => $infoPlusOrder['orderNo'] ?? 'unknown',
                    'message' => 'No status change',
                    'error' => 'Invalid order data from InfoPlus'
                ];
                continue;
            }

            $orderNo = $infoPlusOrder['orderNo'];
            $shopwareOrderId = null;
            foreach ($syncedOrders as $syncedOrder) {
                if ($syncedOrder->getInfoplusId() == $orderNo) {
                    $shopwareOrderId = $syncedOrder->getShopwareOrderId();
                    break;
                }
            }

            if (!$shopwareOrderId) {
                $this->logger->warning('[InfoPlus] No Shopware order found for InfoPlus orderNo', ['orderNo' => $orderNo]);
                $returnArray[] = [
                    'success' => false,
                    'orderNo' => $orderNo,
                    'message' => 'No status change',
                    'error' => 'No Shopware order found for InfoPlus orderNo'
                ];
                continue;
            }

            $status = strtolower($infoPlusOrder['status']);
            $result = [
                'success' => false,
                'orderNo' => $orderNo,
                'message' => 'No status change',
                'error' => null
            ];

            switch ($status) {
                case 'shipped':
                    $result = $this->processOrder($shopwareOrderId, $infoPlusOrder, $context, 'order_delivery', 'ship', 'shipped');
                    break;
                case 'cancelled':
                    $result = $this->processOrder($shopwareOrderId, $infoPlusOrder, $context, 'order_transaction', 'cancel', 'cancelled');
                    if ($result['success']) {
                        $deliveryResult = $this->processOrder($shopwareOrderId, $infoPlusOrder, $context, 'order_delivery', 'cancel', 'cancelled');
                        if (!$deliveryResult['success']) {
                            $result = $deliveryResult;
                        }
                    }
                    break;
                case 'pending':
                case 'unknown':
                case 'on order':
                case 'processed':
                case 'back order':
                    $result = [
                        'success' => true,
                        'orderNo' => $orderNo,
                        'message' => "Order status $status does not require update",
                        'error' => null
                    ];
                    break;
                case 'error':
                    $result = $this->processOrder($shopwareOrderId, $infoPlusOrder, $context, 'order_transaction', 'fail', 'failed');
                    break;
                default:
                    $this->logger->warning('[InfoPlus] Unhandled InfoPlus status', ['orderId' => $shopwareOrderId, 'status' => $status]);
                    $result = [
                        'success' => false,
                        'orderNo' => $orderNo,
                        'message' => 'No status change',
                        'error' => "Unhandled InfoPlus status: $status"
                    ];
            }

            $returnArray[] = $result;
        }

        return $returnArray;
    }

    private function processOrder(string $shopwareOrderId, $infoPlusOrder, Context $context, string $entity, string $action, string $targetState): array
    {
        $orderNo = $infoPlusOrder['orderNo'] ?? 'unknown';

        // Load the order with necessary associations
        $criteria = new Criteria([$shopwareOrderId]);
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('deliveries');
        $order = $this->orderRepository->search($criteria, $context)->first();

        if (!$order instanceof OrderEntity) {
            $this->logger->warning('[InfoPlus] Shopware order not found', ['orderId' => $shopwareOrderId]);
            return [
                'success' => false,
                'orderNo' => $orderNo,
                'message' => 'No status change',
                'error' => 'Shopware order not found'
            ];
        }

        $entityId = null;
        if ($entity === 'order') {
            $entityId = $shopwareOrderId;
        } elseif ($entity === 'order_delivery') {
            $delivery = $order->getDeliveries()->first();
            if (!$delivery) {
                $this->logger->warning('[InfoPlus] No delivery found for order', ['orderId' => $shopwareOrderId]);
                return [
                    'success' => false,
                    'orderNo' => $orderNo,
                    'message' => 'No status change',
                    'error' => 'No delivery found for order'
                ];
            }
            $entityId = $delivery->getId();
        } elseif ($entity === 'order_transaction') {
            $transaction = $order->getTransactions()->first();
            if (!$transaction) {
                $this->logger->warning('[InfoPlus] No transaction found for order', ['orderId' => $shopwareOrderId]);
                return [
                    'success' => false,
                    'orderNo' => $orderNo,
                    'message' => 'No status change',
                    'error' => 'No transaction found for order'
                ];
            }
            $entityId = $transaction->getId();
        }

        try {
            $templateVariables = new ArrayStruct([
                'source' => 'Infoplus',
            ]);
            $context->addExtension('orderStatusToShipped', $templateVariables);
            // Perform state transition
            $transition = new Transition(
                $entity,
                $entityId,
                $action,
                'stateId'
            );

            $this->stateMachineRegistry->transition($transition, $context);
            $this->idMappingService->setShippedStatus($shopwareOrderId, (int)$infoPlusOrder['orderNo'], $context);
            $this->logger->info("[InfoPlus] Order updated to $targetState in Shopware", [
                'orderId' => $shopwareOrderId,
                'entity' => $entity
            ]);

            return [
                'success' => true,
                'orderNo' => $orderNo,
                'message' => "Order updated to $targetState",
                'error' => null
            ];
        } catch (\Exception $e) {
            $this->logger->error("[InfoPlus] Failed to update order to $targetState in Shopware", [
                'orderId' => $shopwareOrderId,
                'entity' => $entity,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'orderNo' => $orderNo,
                'message' => 'No status change',
                'error' => "Failed to update order to $targetState: {$e->getMessage()}"
            ];
        }
    }
}