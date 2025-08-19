<?php

namespace InfoPlusCommerce\Tests\Unit;

use InfoPlusCommerce\Service\SyncService;
use InfoPlusCommerce\Client\InfoplusApiClient;
use InfoPlusCommerce\Service\ConfigService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class SyncServiceTest extends TestCase
{
    use KernelTestBehaviour;

    private $syncService;
    private $infoplusApiClient;
    private $configService;
    private $logger;

    protected function setUp(): void
    {
        $this->infoplusApiClient = $this->createMock(InfoplusApiClient::class);
        $this->configService = $this->createMock(ConfigService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->syncService = new SyncService(
            $this->configService,
            $this->infoplusApiClient,
            $this->logger,
            $this->getContainer()->get('product.repository'),
            $this->getContainer()->get('category.repository'),
            $this->getContainer()->get('customer.repository'),
            $this->getContainer()->get('order.repository')
        );
    }

    public function testSyncProductsMapping(): void
    {
        $this->configService->method('get')->willReturnMap([
            ['lobId', 123],
            ['warehouseId', 456],
        ]);
        $this->infoplusApiClient->method('createItem')->willReturn(['success' => true]);

        $context = Context::createDefaultContext();
        $results = $this->syncService->syncProducts($context);

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('sku', $results[0]);
        $this->assertArrayHasKey('success', $results[0]);
    }
}