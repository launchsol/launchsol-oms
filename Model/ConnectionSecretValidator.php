<?php

declare(strict_types=1);

namespace Launchsol\LaunchOMS\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Phrase;
use Magento\Framework\Webapi\Rest\Request;

/**
 * All /V1/launchoms/* routes are declared `resources: anonymous` in
 * webapi.xml -- Magento's own OAuth/token admin auth doesn't apply to a
 * third-party system like LaunchOMS. Each LaunchOmsApi method calls
 * assertValid() first instead, checking the shared connectionSecret minted
 * during pairing (see ConnectObserver.php) against the X-LaunchOms-Secret
 * header, constant-time via hash_equals() -- the same shared-secret idea
 * PushOrderObserver's own webhook_secret uses, just in the other direction.
 */
class ConnectionSecretValidator
{
    private const XML_PATH_CONNECTION_SECRET = 'launchoms_order_push/general/connection_secret';
    private const HEADER = 'X-LaunchOms-Secret';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Request $request
    ) {
    }

    public function assertValid(): void
    {
        $expected = (string) $this->scopeConfig->getValue(self::XML_PATH_CONNECTION_SECRET);
        $provided = (string) $this->request->getHeader(self::HEADER);

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            throw new AuthorizationException(new Phrase('Invalid or missing %1 header.', [self::HEADER]));
        }
    }
}
