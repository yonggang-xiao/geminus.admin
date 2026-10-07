<?php

namespace Geminus\Admin\Config;

use CodeIgniter\Config\BaseConfig;
use TheNetworg\OAuth2\Client\Provider\Azure;

class MicrosoftOAuth extends BaseConfig
{
    public const ENDPOINT_VERSION = Azure::ENDPOINT_VERSION_2_0;

    public bool $enabled        = false;
    public string $tenant       = 'organizations';
    public string $clientId     = '';
    public string $clientSecret = '';
}
