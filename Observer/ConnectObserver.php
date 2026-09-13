<?php

declare(strict_types=1);

namespace Launchsol\LaunchOMS\Observer;

use Magento\Config\Model\ResourceModel\Config as ConfigResource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Fires whenever Stores > Configuration > Service > LaunchOMS Order Push is
 * saved. If a Connection Token was pasted in, redeems it against LaunchOMS's
 * POST /api/integrations/magento/register -- see src/app/api/integrations/
 * magento/register/route.ts and src/app/(dashboard)/marketplace/
 * _components/MagentoConnectionCard.tsx (which mints the token) on the
 * LaunchOMS side. On success, stores the returned connectionId/
 * connectionSecret (used later by LaunchOmsApi.php for the inbound
 * webapi routes) and clears the one-time token.
 */
class ConnectObserver implements ObserverInterface
{
    private const XML_PATH_LAUNCHOMS_URL = 'launchoms_order_push/general/launchoms_url';
    private const XML_PATH_CONNECTION_TOKEN = 'launchoms_order_push/general/connection_token';
    private const XML_PATH_CONNECTION_ID = 'launchoms_order_push/general/connection_id';
    private const XML_PATH_CONNECTION_SECRET = 'launchoms_order_push/general/connection_secret';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ConfigResource $configResource,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductMetadataInterface $productMetadata,
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(EventObserver $observer): void
    {
        $token = trim((string) $this->scopeConfig->getValue(self::XML_PATH_CONNECTION_TOKEN));
        if ($token === '') {
            // No token pasted -- this save wasn't a connect attempt.
            return;
        }

        $launchOmsUrl = rtrim((string) $this->scopeConfig->getValue(self::XML_PATH_LAUNCHOMS_URL), '/');
        if ($launchOmsUrl === '') {
            $this->messageManager->addErrorMessage(
                'LaunchOMS Order Push: set the LaunchOMS URL field before pasting a connection token.'
            );
            return;
        }

        try {
            $stores = [];
            foreach ($this->storeManager->getStores() as $store) {
                $stores[] = [
                    'code' => $store->getCode(),
                    'name' => $store->getName(),
                    'websiteCode' => $store->getWebsite()->getCode(),
                    'storeId' => (int) $store->getId(),
                ];
            }

            $payload = $this->json->serialize([
                'token' => $token,
                'baseUrl' => $this->storeManager->getStore()->getBaseUrl(),
                'magentoVersion' => $this->productMetadata->getVersion(),
                'stores' => $stores,
            ]);

            $this->curl->setTimeout(10);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->post($launchOmsUrl . '/api/integrations/magento/register', $payload);

            $status = $this->curl->getStatus();
            $body = $this->curl->getBody();

            if ($status !== 200) {
                $this->logger->error('LaunchOMS connect failed', ['status' => $status, 'body' => $body]);
                $this->messageManager->addErrorMessage(
                    'LaunchOMS Order Push: could not connect (HTTP ' . $status . '). '
                    . 'Get a fresh connection token from LaunchOMS and try again.'
                );
                return;
            }

            $data = $this->json->unserialize($body);
            $connectionId = $data['connectionId'] ?? null;
            $connectionSecret = $data['connectionSecret'] ?? null;
            if (!is_string($connectionId) || !is_string($connectionSecret) || $connectionId === '' || $connectionSecret === '') {
                throw new \RuntimeException('Malformed response from LaunchOMS.');
            }

            $this->configResource->saveConfig(self::XML_PATH_CONNECTION_ID, $connectionId, 'default', 0);
            $this->configResource->saveConfig(self::XML_PATH_CONNECTION_SECRET, $connectionSecret, 'default', 0);
            // Single-use -- clear it so the next unrelated config save
            // doesn't try to redeem an already-spent token again.
            $this->configResource->saveConfig(self::XML_PATH_CONNECTION_TOKEN, '', 'default', 0);

            $this->messageManager->addSuccessMessage(
                'Connected to LaunchOMS. Go to LaunchOMS > Marketplace > Magento to pick which stores to enable.'
            );
        } catch (\Throwable $e) {
            $this->logger->error('LaunchOMS connect failed', ['exception' => $e->getMessage()]);
            $this->messageManager->addErrorMessage('LaunchOMS Order Push: connect failed -- ' . $e->getMessage());
        }
    }
}
