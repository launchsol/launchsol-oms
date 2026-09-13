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
    public function getStoreCode(): string;

    public function setStoreCode(string $storeCode): self;

    public function getChannelId(): string;

    public function setChannelId(string $channelId): self;

    public function getEnabled(): bool;

    public function setEnabled(bool $enabled): self;

    public function getWebhookUrl(): string;

    public function setWebhookUrl(string $webhookUrl): self;

    public function getWebhookSecret(): string;

    public function setWebhookSecret(string $webhookSecret): self;
}
