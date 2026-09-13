<?php

declare(strict_types=1);

namespace Launchsol\LaunchOMS\Api;

use Launchsol\LaunchOMS\Api\Data\ChannelMapEntryInterface;
use Launchsol\LaunchOMS\Api\Data\StoreViewInterface;

/**
 * The LaunchOMS-facing half of the pairing flow -- everything under
 * /rest/V1/launchoms/*. Declared `resources: anonymous` in webapi.xml
 * (Magento's own OAuth/token auth doesn't apply to a third-party system
 * like LaunchOMS); every method instead validates the shared
 * connectionSecret from pairing against the X-LaunchOMS-Secret header
 * itself -- see \Launchsol\LaunchOMS\Model\ConnectionSecretValidator.
 */
interface LaunchOMSInterface
{
    /**
     * Store views this Magento instance has -- lets LaunchOMS's "Refresh
     * stores" pick up ones added after the initial pairing.
     *
     * @return \Launchsol\LaunchOMS\Api\Data\StoreViewInterface[]
     */
    public function getStores(): array;

    /**
     * Writes webhook_url/webhook_secret/enabled into each named store
     * view's own scoped config -- the fields Model\Config.php /
     * Observer\PushOrderObserver.php already read, previously pasted in by
     * hand. Called once per LaunchOMS-side "Save" in the store picker.
     *
     * @param \Launchsol\LaunchOMS\Api\Data\ChannelMapEntryInterface[] $mappings
     * @return bool
     */
    public function setChannelMap(array $mappings): bool;

    /**
     * Writes one SKU's available quantity at a source, via core MSI --
     * the seamless replacement for LaunchOMS calling Magento's own
     * admin-token-gated POST /V1/inventory/source-items directly.
     *
     * @param string $sku
     * @param string $sourceCode
     * @param int $quantity
     * @return bool
     */
    public function pushInventory(string $sku, string $sourceCode, int $quantity): bool;
}
