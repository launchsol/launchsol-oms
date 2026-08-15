<?php

declare(strict_types=1);

namespace LaunchOms\OrderPush\Observer;

use LaunchOms\OrderPush\Model\Config;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Pushes a newly placed order to LaunchOMS's Magento webhook endpoint
 * (POST /api/webhooks/magento/<channelId>/orders-create -- see
 * src/app/api/webhooks/magento/[channelId]/orders-create/route.ts and
 * src/lib/validation/magento.schema.ts on the LaunchOMS side).
 *
 * Never blocks or fails checkout: every failure mode here is caught and
 * logged (var/log/launchoms_order_push.log), never rethrown -- a customer's
 * order must always complete even if LaunchOMS is unreachable. The webhook
 * endpoint is idempotent (keyed on increment_id per channel), so replaying
 * a failed push from the log is always safe and won't create a duplicate
 * order on the LaunchOMS side.
 */
class PushOrderObserver implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly Curl $curl,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(EventObserver $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $webhookUrl = $this->config->getWebhookUrl();
        $webhookSecret = $this->config->getWebhookSecret();
        if (!$webhookUrl || !$webhookSecret) {
            $this->logger->warning(
                'Enabled but Webhook URL or Webhook Secret is not configured (Stores > Configuration '
                . '> Service > LaunchOMS Order Push) -- skipping push.'
            );
            return;
        }

        /** @var Order|null $order */
        $order = $observer->getEvent()->getOrder();
        if (!$order || !$order->getId()) {
            return;
        }

        try {
            $payload = $this->buildPayload($order);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'Failed to build payload for order %s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
            return;
        }

        try {
            $this->curl->setTimeout($this->config->getTimeout());
            $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 5);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->addHeader('X-Magento-Webhook-Secret', $webhookSecret);
            $this->curl->post($webhookUrl, json_encode($payload, JSON_THROW_ON_ERROR));

            $status = $this->curl->getStatus();
            if ($status >= 200 && $status < 300) {
                $this->logger->info(sprintf(
                    'Pushed order %s to LaunchOMS (HTTP %d).',
                    $order->getIncrementId(),
                    $status
                ));
            } else {
                $this->logger->error(sprintf(
                    'LaunchOMS rejected order %s: HTTP %d %s',
                    $order->getIncrementId(),
                    $status,
                    $this->curl->getBody()
                ));
            }
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'Failed to push order %s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
        }
    }

    /**
     * Shape must match src/lib/validation/magento.schema.ts on the
     * LaunchOMS side exactly -- change both together if either changes.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(Order $order): array
    {
        $shippingAddress = $order->getShippingAddress();
        if (!$shippingAddress) {
            throw new \RuntimeException(
                'Order has no shipping address (e.g. a virtual/downloadable-only order) -- not pushed.'
            );
        }

        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'sku' => (string) $item->getSku(),
                'qty_ordered' => (float) $item->getQtyOrdered(),
                'price' => (float) $item->getPrice(),
            ];
        }

        if (empty($items)) {
            throw new \RuntimeException('Order has no visible line items.');
        }

        $street = array_values(array_filter((array) $shippingAddress->getStreet()));
        if (empty($street)) {
            throw new \RuntimeException('Shipping address has no street lines.');
        }

        $regionCode = $shippingAddress->getRegionCode();
        if (!$regionCode) {
            // Some regions/countries don't carry a region_code (e.g. a
            // free-text region on an address outside the predefined region
            // table) -- fall back to the plain region name rather than
            // sending an empty string.
            $regionCode = $shippingAddress->getRegion();
        }

        return [
            'entity_id' => (int) $order->getEntityId(),
            'increment_id' => (string) $order->getIncrementId(),
            'grand_total' => (float) $order->getGrandTotal(),
            'items' => $items,
            'extension_attributes' => [
                'shipping_assignments' => [
                    [
                        'shipping' => [
                            'address' => [
                                'firstname' => (string) $shippingAddress->getFirstname(),
                                'lastname' => (string) $shippingAddress->getLastname(),
                                'street' => $street,
                                'city' => (string) $shippingAddress->getCity(),
                                'region_code' => (string) $regionCode,
                                'postcode' => (string) $shippingAddress->getPostcode(),
                                'country_id' => (string) $shippingAddress->getCountryId(),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
