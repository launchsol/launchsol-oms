# Launchsol_LaunchOMS

A Magento 2 module that pairs a Magento store with a LaunchOMS account and
keeps them in sync in both directions:

- **Inbound**: pushes every newly placed order to LaunchOMS's webhook
  endpoint (`POST /api/webhooks/magento/<channelId>/orders-create`).
- **Outbound**: exposes a small token-authenticated API
  (`GET/POST /rest/V1/launchoms/*`) that LaunchOMS calls to discover store
  views and push inventory quantities back -- see
  `src/lib/channels/magento.real.ts` on the LaunchOMS side.

The only manual step is pasting a one-time connection token from LaunchOMS
into this module's config and saving -- from there, store discovery,
per-store webhook URL/secret, and inventory sync are all provisioned
automatically. No Magento Integration/admin token is ever needed.

## What it does

- Observes `checkout_submit_all_after` -- fires exactly once per
  successfully placed order (not on every order save, unlike
  `sales_order_save_after`).
- Builds a JSON payload shaped like Magento's own REST Sales Order API
  (`entity_id`, `increment_id`, `grand_total`, `items[]`,
  `extension_attributes.shipping_assignments[0].shipping.address`) -- see
  `Observer/PushOrderObserver.php::buildPayload()`.
- POSTs it to this store's configured Webhook URL with a shared secret in
  the `X-Magento-Webhook-Secret` header. Never blocks or fails checkout:
  every failure (network error, timeout, non-2xx response, malformed
  order) is caught and logged to `var/log/launchsol_launchoms.log`, never
  rethrown. The LaunchOMS webhook is idempotent per `increment_id`, so
  replaying a failed push is always safe.
- Exposes `GET /V1/launchoms/stores`, `POST /V1/launchoms/channel-map`, and
  `POST /V1/launchoms/inventory` (see `Api/LaunchOMSInterface.php` /
  `Model/LaunchOMSApi.php`), each authenticated by comparing the shared
  `connectionSecret` from pairing against an `X-LaunchOMS-Secret` header
  (`Model/ConnectionSecretValidator.php`) rather than Magento's own
  admin/OAuth auth, since the caller is LaunchOMS, not a Magento user.

## Install

**Via Composer** (once published on Packagist -- see "Publishing" below):

```bash
composer require launchsol/launchsol-oms
bin/magento module:enable Launchsol_LaunchOMS
bin/magento setup:upgrade
bin/magento setup:di:compile      # production mode only
bin/magento cache:flush
```

**Or manually**, from your Magento root:

```bash
mkdir -p app/code/Launchsol/LaunchOMS
cp -r /path/to/launchsol-oms/* app/code/Launchsol/LaunchOMS/
bin/magento module:enable Launchsol_LaunchOMS
bin/magento setup:upgrade
bin/magento setup:di:compile      # production mode only
bin/magento cache:flush
```

### Publishing (for maintainers)

The package name in `composer.json` (`launchsol/launchsol-oms`) matches
this GitHub repo (`launchsol/launchsol-oms`), which is public, so
`composer require launchsol/launchsol-oms` works from *any* Magento
project with no extra `repositories` config, once the package is
registered on [Packagist](https://packagist.org/packages/submit) (one-time,
requires a Packagist account linked to this GitHub org -- Packagist then
auto-updates on every push via its GitHub webhook). Until then, a
consumer needs a `vcs` repository entry pointing at this repo's URL
instead.

Cut a release by tagging a commit (`git tag v1.1.0 && git push --tags`) --
Packagist and Composer resolve installable versions from tags, not
branches.

## Connect

1. In **LaunchOMS**: Marketplace > Magento > **Connect a Magento store**.
   This shows your LaunchOMS URL and a one-time connection token (valid
   15 minutes).
2. In the **Magento admin**: Stores > Configuration > Service > LaunchOMS
   Order Push.
   - **LaunchOMS URL**: paste the URL shown in LaunchOMS.
   - **Connection Token**: paste the token shown in LaunchOMS.
3. Click **Save Config**. On success you'll see a "Connected to LaunchOMS"
   message, and the token field clears itself (it's single-use). Behind
   the scenes, this store's full list of store views was sent to LaunchOMS
   and a long-lived secret was stored here for the two API directions
   above -- see `Observer/ConnectObserver.php`.
4. Back in **LaunchOMS**: the connected instance now appears under
   Marketplace > Magento with its store views listed. Check the ones you
   want to sync, give each a channel name, and **Save**. LaunchOMS creates
   one Channel per enabled store and pushes each one's webhook URL/secret
   back into this module automatically -- **Enabled**, **Webhook URL**,
   and **Webhook Secret** in Magento's config will already be filled in
   per store, nothing left to copy by hand.
5. Place a test order in an enabled store and check `/channel-health` on
   the LaunchOMS side, or tail `var/log/launchsol_launchoms.log` here, to
   confirm the push succeeded.

If a store view is added in Magento later, use "Refresh stores" next to
the connection in LaunchOMS's Marketplace to pick it up without re-pairing.

### Legacy manual setup

The old fully-manual path (LaunchOMS's `scripts/configure-magento.ts` +
hand-pasted Webhook URL/Secret/access token) still works for existing
installs and isn't required to change -- it's just no longer how a new
connection gets set up.

## Keeping the payload shape in sync

If `src/lib/validation/magento.schema.ts` on the LaunchOMS side ever
changes, update `PushOrderObserver::buildPayload()` to match -- there's a
comment at each end pointing at the other. Likewise, LaunchOMS's webapi
framework converts Data Interface fields to snake_case on the wire
(`getWebsiteCode()` -> `website_code`); the LaunchOMS-side callers already
account for this (see comments in `src/lib/channels/magentoConnect.ts` and
the `connections/[id]` API routes) -- keep both ends in sync if a field is
renamed on either side.
