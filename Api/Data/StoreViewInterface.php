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
    public function getCode(): string;

    public function setCode(string $code): self;

    public function getName(): string;

    public function setName(string $name): self;

    public function getWebsiteCode(): string;

    public function setWebsiteCode(string $websiteCode): self;

    public function getStoreId(): int;

    public function setStoreId(int $storeId): self;
}
