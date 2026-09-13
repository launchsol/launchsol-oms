<?php

declare(strict_types=1);

namespace LaunchOms\OrderPush\Model\Data;

use LaunchOms\OrderPush\Api\Data\ChannelMapEntryInterface;
use Magento\Framework\DataObject;

class ChannelMapEntry extends DataObject implements ChannelMapEntryInterface
{
    public function getStoreCode(): string
    {
        return (string) $this->getData('store_code');
    }

    public function setStoreCode(string $storeCode): self
    {
        return $this->setData('store_code', $storeCode);
    }

    public function getChannelId(): string
    {
        return (string) $this->getData('channel_id');
    }

    public function setChannelId(string $channelId): self
    {
        return $this->setData('channel_id', $channelId);
    }

    public function getEnabled(): bool
    {
        return (bool) $this->getData('enabled');
    }

    public function setEnabled(bool $enabled): self
    {
        return $this->setData('enabled', $enabled);
    }

    public function getWebhookUrl(): string
    {
        return (string) $this->getData('webhook_url');
    }

    public function setWebhookUrl(string $webhookUrl): self
    {
        return $this->setData('webhook_url', $webhookUrl);
    }

    public function getWebhookSecret(): string
    {
        return (string) $this->getData('webhook_secret');
    }

    public function setWebhookSecret(string $webhookSecret): self
    {
        return $this->setData('webhook_secret', $webhookSecret);
    }
}
