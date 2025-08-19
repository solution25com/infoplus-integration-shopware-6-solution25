<?php declare(strict_types=1);

namespace InfoPlusCommerce\Command;

use InfoPlusCommerce\Message\SyncOrdersMessage;
use InfoPlusCommerce\Service\SyncService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'infoplus:sync',
    description: 'Sync all entities (customers, categories, products, orders) with InfoPlus in sequence'
)]
class SyncCommand extends Command
{
    public function __construct(
        private readonly SyncService $syncService,
        private readonly LoggerInterface $logger,
        private readonly EntityRepository $orderRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('[InfoPlus] Full sync command triggered');
        $output->writeln('Starting full synchronization with InfoPlus...');
        $context = Context::createDefaultContext();

        $this->syncService->getConfigService()->set('syncInProgress', true);
        // Step 1: Sync Customers
        $output->writeln('Synchronizing customers...');
        try {
            $customerResults = $this->syncService->syncCustomers($context);
            if (isset($customerResults['status']) && $customerResults['status'] === 'no customers found') {
                $output->writeln('No customers found for synchronization.');
                $this->logger->warning('[InfoPlus] No customers found for sync');
            } else {
                foreach ($customerResults as $result) {
                    if ($result['success']) {
                        $output->writeln(sprintf('Customer %s synced successfully.', $result['customerNo']));
                    } else {
                        $output->writeln(sprintf('Failed to sync customer %s: %s', $result['customerNo'], $result['error']));
                        $this->logger->error('[InfoPlus] Failed to sync customer', ['customerNo' => $result['customerNo'], 'error' => $result['error']]);
                    }
                }
            }
            $output->writeln('Customer synchronization completed.');
        } catch (\Exception $e) {
            $this->syncService->getConfigService()->set('syncInProgress', false);
            $output->writeln(sprintf('Customer sync failed: %s', $e->getMessage()));
            $this->logger->error('[InfoPlus] Customer sync failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }

        // Step 2: Sync Categories
        $output->writeln('Synchronizing categories...');
        try {
            $categoryResults = $this->syncService->syncCategories($context);
            if (isset($categoryResults['status']) && $categoryResults['status'] === 'no categories found') {
                $output->writeln('No categories found for synchronization.');
                $this->logger->warning('[InfoPlus] No categories found for sync');
            } else {
                foreach ($categoryResults as $result) {
                    if ($result['success']) {
                        $output->writeln(sprintf('Category %s (%s) synced successfully.', $result['name'], $result['type']));
                    } else {
                        $output->writeln(sprintf('Failed to sync category %s (%s): %s', $result['name'], $result['type'], $result['error']));
                        $this->logger->error('[InfoPlus] Failed to sync category', ['name' => $result['name'], 'type' => $result['type'], 'error' => $result['error']]);
                    }
                }
            }
            $output->writeln('Category synchronization completed.');
        } catch (\Exception $e) {
            $this->syncService->getConfigService()->set('syncInProgress', false);
            $output->writeln(sprintf('Category sync failed: %s', $e->getMessage()));
            $this->logger->error('[InfoPlus] Category sync failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }

        // Step 3: Sync Products
        $output->writeln('Synchronizing products...');
        try {
            $productResults = $this->syncService->syncProducts($context);
            if (isset($productResults['status']) && $productResults['status'] === 'no products found') {
                $output->writeln('No products found for synchronization.');
                $this->logger->warning('[InfoPlus] No products found for sync');
            } else {
                foreach ($productResults as $result) {
                    if ($result['success']) {
                        $output->writeln(sprintf('Product %s synced successfully.', $result['sku']));
                    } else {
                        $output->writeln(sprintf('Failed to sync product %s: %s', $result['sku'], $result['error']));
                        $this->logger->error('[InfoPlus] Failed to sync product', ['sku' => $result['sku'], 'error' => $result['error']]);
                    }
                }
            }
            $output->writeln('Product synchronization completed.');
        } catch (\Exception $e) {
            $this->syncService->getConfigService()->set('syncInProgress', false);
            $output->writeln(sprintf('Product sync failed: %s', $e->getMessage()));
            $this->logger->error('[InfoPlus] Product sync failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }

        // Step 4: Sync Orders
        $output->writeln('Synchronizing orders...');
        try {
            $criteria = new Criteria();
            $criteria->addAssociation('lineItems.product');
            $criteria->addAssociation('shippingAddress');
            $criteria->addAssociation('orderCustomer');
            $orders = $this->orderRepository->search($criteria, $context);
            $orderIds = array_values($orders->getIds());

            if (empty($orderIds)) {
                $output->writeln('No orders found for synchronization.');
                $this->logger->warning('[InfoPlus] No orders found for sync');
            } else {
                $orderResults = $this->syncService->syncOrders($orderIds, $context);
                if (isset($orderResults['status']) && $orderResults['status'] === 'no order found') {
                    $output->writeln('No order found for synchronization.');
                    $this->logger->warning('[InfoPlus] No orders found for sync');
                } else {
                    foreach ($productResults as $result) {
                        if ($result['success']) {
                            $output->writeln(sprintf('Order %s synced successfully.', $result['orderNo'] ?? $result['sku']?? "-"));
                        } else {
                            $output->writeln(sprintf('Failed to sync product %s: %s', $result['orderNo'] ?? $result['sku']?? "-", $result['error']));
                            $this->logger->error('[InfoPlus] Failed to sync product', ['sku' => $result['sku'], 'error' => $result['error']]);
                        }
                    }
                }
            }
            $output->writeln('Order synchronization dispatched.');
        } catch (\Exception $e) {
            $this->syncService->getConfigService()->set('syncInProgress', false);
            $output->writeln(sprintf('Order sync failed: %s', $e->getMessage()));
            $this->logger->error('[InfoPlus] Order sync failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }

        $this->syncService->getConfigService()->set('syncInProgress', false);
        $this->syncService->getConfigService()->set('lastSyncTime', date('Y-m-d H:i:s'));
        $output->writeln('Full synchronization with InfoPlus completed.');
        $this->logger->info('[InfoPlus] Full sync command completed');
        return Command::SUCCESS;
    }
}