<?php

namespace Geminus\Admin\Config;

use CodeIgniter\Config\BaseConfig;

class MicrosoftOAuth extends BaseConfig
{
    public string $tenant       = '';
    public string $clientId     = '';
    public string $clientSecret = '';
}
