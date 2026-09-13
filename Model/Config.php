<?php

declare(strict_types=1);

namespace Launchsol\LaunchOMS\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Thin wrapper around Stores > Configuration > Service > LaunchOMS Order
 * Push (etc/adminhtml/system.xml). Store-scoped since a multi-store Magento
 * install may push different stores to different LaunchOMS channels.
 */
class Config
{
    private const XML_PATH_ENABLED = 'launchoms_order_push/general/enabled';
    private const XML_PATH_WEBHOOK_URL = 'launchoms_order_push/general/webhook_url';
    private const XML_PATH_WEBHOOK_SECRET = 'launchoms_order_push/general/webhook_secret';
    private const XML_PATH_TIMEOUT = 'launchoms_order_push/general/timeout';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getWebhookUrl(?int $storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_WEBHOOK_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value !== null && $value !== '' ? rtrim((string) $value, '/') : null;
    }

    public function getWebhookSecret(?int $storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_WEBHOOK_SECRET,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function getTimeout(?int $storeId = null): int
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_TIMEOUT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value !== null && $value !== '' ? (int) $value : 5;
    }
}
