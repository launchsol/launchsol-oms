<?php

declare(strict_types=1);

namespace Launchsol\LaunchOMS\Model\Data;

use Launchsol\LaunchOMS\Api\Data\StoreViewInterface;
use Magento\Framework\DataObject;

class StoreView extends DataObject implements StoreViewInterface
{
    public function getCode(): string
    {
        return (string) $this->getData('code');
    }

    public function setCode(string $code): self
    {
        return $this->setData('code', $code);
    }

    public function getName(): string
    {
        return (string) $this->getData('name');
    }

    public function setName(string $name): self
    {
        return $this->setData('name', $name);
    }

    public function getWebsiteCode(): string
    {
        return (string) $this->getData('website_code');
    }

    public function setWebsiteCode(string $websiteCode): self
    {
        return $this->setData('website_code', $websiteCode);
    }

    public function getStoreId(): int
    {
        return (int) $this->getData('store_id');
    }

    public function setStoreId(int $storeId): self
    {
        return $this->setData('store_id', $storeId);
    }
}
