<?php

declare(strict_types=1);

namespace Launchsol\LaunchOMS\Model;

use Launchsol\LaunchOMS\Api\Data\StoreViewInterfaceFactory;
use Launchsol\LaunchOMS\Api\LaunchOmsInterface;
use Magento\Config\Model\ResourceModel\Config as ConfigResource;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class LaunchOmsApi implements LaunchOmsInterface
{
    public function __construct(
        private readonly ConnectionSecretValidator $secretValidator,
        private readonly StoreManagerInterface $storeManager,
        private readonly StoreViewInterfaceFactory $storeViewFactory,
        private readonly ConfigResource $configResource,
        private readonly SourceItemsSaveInterface $sourceItemsSave,
        private readonly SourceItemInterfaceFactory $sourceItemFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getStores(): array
    {
        $this->secretValidator->assertValid();

        $result = [];
        foreach ($this->storeManager->getStores() as $store) {
            /** @var \Launchsol\LaunchOMS\Model\Data\StoreView $view */
            $view = $this->storeViewFactory->create();
            $view->setCode($store->getCode())
                ->setName($store->getName())
                ->setWebsiteCode($store->getWebsite()->getCode())
                ->setStoreId((int) $store->getId());
            $result[] = $view;
        }

        return $result;
    }

    public function setChannelMap(array $mappings): bool
    {
        $this->secretValidator->assertValid();

        foreach ($mappings as $entry) {
            $storeId = $this->resolveStoreId($entry->getStoreCode());
            if ($storeId === null) {
                $this->logger->warning(
                    'LaunchOMS channel-map: unknown store code, skipped',
                    ['storeCode' => $entry->getStoreCode()]
                );
                continue;
            }

            $this->configResource->saveConfig(
                'launchoms_order_push/general/enabled',
                $entry->getEnabled() ? '1' : '0',
                'stores',
                $storeId
            );
            $this->configResource->saveConfig(
                'launchoms_order_push/general/webhook_url',
                $entry->getWebhookUrl(),
                'stores',
                $storeId
            );
            $this->configResource->saveConfig(
                'launchoms_order_push/general/webhook_secret',
                $entry->getWebhookSecret(),
                'stores',
                $storeId
            );
        }

        return true;
    }

    public function pushInventory(string $sku, string $sourceCode, int $quantity): bool
    {
        $this->secretValidator->assertValid();

        $item = $this->sourceItemFactory->create();
        $item->setSku($sku);
        $item->setSourceCode($sourceCode);
        $item->setQuantity((float) $quantity);
        $item->setStatus($quantity > 0 ? 1 : 0);

        $this->sourceItemsSave->execute([$item]);

        return true;
    }

    private function resolveStoreId(string $storeCode): ?int
    {
        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getCode() === $storeCode) {
                return (int) $store->getId();
            }
        }

        return null;
    }
}
