<?php

declare(strict_types=1);

namespace Launchsol\LaunchOMS\Api\Data;

/**
 * Mirrors the {code, name, websiteCode, storeId} shape LaunchOMS's
 * MagentoConnection.stores cache and store-picker UI expect -- see
 * MagentoStoreView in src/lib/channels/magentoConnect.ts.
 */
interface StoreViewInterface
{
    /**
     * @return string
     */
    public function getCode(): string;

    /**
     * @param string $code
     * @return $this
     */
    public function setCode(string $code): self;

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;

    /**
     * @return string
     */
    public function getWebsiteCode(): string;

    /**
     * @param string $websiteCode
     * @return $this
     */
    public function setWebsiteCode(string $websiteCode): self;

    /**
     * @return int
     */
    public function getStoreId(): int;

    /**
     * @param int $storeId
     * @return $this
     */
    public function setStoreId(int $storeId): self;
}
