<?php

declare(strict_types=1);

namespace Launchsol\LaunchOMS\Api\Data;

/**
 * One store view's LaunchOMS Channel mapping, pushed down after the
 * merchant enables/renames/disables stores in LaunchOMS's Marketplace
 * store picker -- see connections/[id]/channels/route.ts on the LaunchOMS
 * side, which builds these from each Channel's own webhook credentials.
 */
interface ChannelMapEntryInterface
{
    /**
     * @return string
     */
    public function getStoreCode(): string;

    /**
     * @param string $storeCode
     * @return $this
     */
    public function setStoreCode(string $storeCode): self;

    /**
     * @return string
     */
    public function getChannelId(): string;

    /**
     * @param string $channelId
     * @return $this
     */
    public function setChannelId(string $channelId): self;

    /**
     * @return bool
     */
    public function getEnabled(): bool;

    /**
     * @param bool $enabled
     * @return $this
     */
    public function setEnabled(bool $enabled): self;

    /**
     * @return string
     */
    public function getWebhookUrl(): string;

    /**
     * @param string $webhookUrl
     * @return $this
     */
    public function setWebhookUrl(string $webhookUrl): self;

    /**
     * @return string
     */
    public function getWebhookSecret(): string;

    /**
     * @param string $webhookSecret
     * @return $this
     */
    public function setWebhookSecret(string $webhookSecret): self;
}
