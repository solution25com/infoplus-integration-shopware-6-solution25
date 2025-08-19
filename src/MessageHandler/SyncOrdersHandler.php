<?php

namespace InfoPlusCommerce\MessageHandler;

use InfoPlusCommerce\Message\SyncOrdersMessage;
use InfoPlusCommerce\Service\SyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SyncOrdersHandler
{
    public function __construct(
        private readonly SyncService $syncService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(SyncOrdersMessage $message): void
    {
        $this->logger->info('[InfoPlus] Starting background order sync', [
            'channel' => 'infoplus',
            'orderIds' => $message->getOrderIds()
        ]);
        $context = $message->getContext();
        $orderIds = $message->getOrderIds();

        $result = $this->syncService->syncOrders($orderIds, $context);
        $this->logger->info('[InfoPlus] Background order sync completed', [
            'channel' => 'infoplus',
            'orderIds' => $orderIds,
            'result' => $result
        ]);
    }
}