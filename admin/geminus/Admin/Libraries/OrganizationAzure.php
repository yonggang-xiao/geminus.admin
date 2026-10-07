<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries;

use RuntimeException;
use TheNetworg\OAuth2\Client\Provider\Azure;

class OrganizationAzure extends Azure
{
    public function validateTokenClaims($tokenClaims)
    {
        $tenant = $tokenClaims['tid'] ?? null;
        $object = $tokenClaims['oid'] ?? null;
        $guid   = '/\A[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}\z/i';

        if (($tokenClaims['ver'] ?? null) !== self::ENDPOINT_VERSION_2_0
            || ! is_string($tenant) || ! preg_match($guid, $tenant)
            || ! is_string($object) || ! preg_match($guid, $object)
            || strcasecmp($tenant, '9188040d-6c67-4c5b-b112-36a304b66dad') === 0
            || ($this->tenant !== 'organizations' && strcasecmp($tenant, $this->tenant) !== 0)) {
            throw new RuntimeException('Invalid organizational Microsoft identity.');
        }

        $this->tenant = $tenant;
        parent::validateTokenClaims($tokenClaims);
    }
}
