# LaunchOms_OrderPush

A Magento 2 module that pushes every newly placed order to a LaunchOMS
webhook endpoint (`POST /api/webhooks/magento/<channelId>/orders-create`).
It's the missing half of `src/lib/channels/magento.real.ts` on the LaunchOMS
side, which already handles the *outbound* direction (pushing stock levels
to Magento) -- this module handles *inbound* (pushing orders to LaunchOMS).

Magento has no built-in outbound webhook subscription mechanism (unlike
Shopify), so this exists to fill that gap with a small, focused module
rather than requiring custom middleware.

## What it does

- Observes `checkout_submit_all_after` -- fires exactly once per
  successfully placed order (not on every order save, unlike
  `sales_order_save_after`).
- Builds a JSON payload shaped like Magento's own REST Sales Order API
  (`entity_id`, `increment_id`, `grand_total`, `items[]`,
  `extension_attributes.shipping_assignments[0].shipping.address`) -- see
  `Observer/PushOrderObserver.php::buildPayload()`.
- POSTs it to the configured Webhook URL with the shared secret in the
  `X-Magento-Webhook-Secret` header.
- Never blocks or fails checkout: every failure (network error, timeout,
  non-2xx response, malformed order) is caught and logged to
  `var/log/launchoms_order_push.log`, never rethrown. The LaunchOMS webhook
  is idempotent per `increment_id`, so replaying a failed push is always
  safe.

## Install

From your Magento root:

```bash
mkdir -p app/code/LaunchOms/OrderPush
cp -r /path/to/launchoms/magento-module/* app/code/LaunchOms/OrderPush/
bin/magento module:enable LaunchOms_OrderPush
bin/magento setup:upgrade
bin/magento setup:di:compile      # production mode only
bin/magento cache:flush
```

(Or require it via a private Composer repository pointing at this
directory instead of copying files -- `composer.json` is already set up as
a `magento2-module` package.)

## Configure

1. On the **LaunchOMS** side, set env vars and run the configure script to
   create/update the `magento` Channel row and get the exact webhook URL:

   ```bash
   export MAGENTO_WEBHOOK_SECRET="choose-a-random-secret"
   npm run configure:magento
   ```

   It prints something like:

   ```
   https://your-launchoms-host/api/webhooks/magento/cmXXXXXXXXXXXX/orders-create
   ```

2. In the **Magento admin**: Stores > Configuration > Service > LaunchOMS
   Order Push.
   - **Enabled**: Yes
   - **Webhook URL**: the exact URL printed above
   - **Webhook Secret**: the same value as `MAGENTO_WEBHOOK_SECRET`
   - **Request timeout**: 5 (default is fine for most setups)

3. Save config, then `bin/magento cache:flush`.

4. Place a test order and check
   `/channel-health` on the LaunchOMS side, or tail
   `var/log/launchoms_order_push.log` on the Magento side, to confirm the
   push succeeded.

## Multi-store / multi-account

Every config field is store-scoped (`showInStore="1"`), so a Magento
instance running multiple stores/websites can point each one at a
different LaunchOMS channelId -- useful if different stores belong to
different LaunchOMS accounts (tenants).

## Keeping the payload shape in sync

If `src/lib/validation/magento.schema.ts` on the LaunchOMS side ever
changes, update `PushOrderObserver::buildPayload()` to match -- there's a
comment at each end pointing at the other.
