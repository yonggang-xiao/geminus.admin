<?php

declare(strict_types=1);

namespace Geminus\Admin\Config;

use CodeIgniter\Config\BaseConfig;
use InvalidArgumentException;

class Dashboard extends BaseConfig
{
    public array $providers = [];

    protected function registerProperties()
    {
        $providers = $this->providers;
        parent::registerProperties();

        foreach (static::$registrars as $registrar) {
            if (! method_exists($registrar, 'Dashboard')) {
                continue;
            }

            $registration = $registrar::Dashboard();
            if (array_diff(array_keys($registration), ['providers']) !== [] || ! is_array($registration['providers'] ?? null)) {
                throw new InvalidArgumentException('Dashboard registrar must declare a providers array.');
            }

            foreach ($registration['providers'] as $identifier => $provider) {
                if (array_key_exists($identifier, $providers)) {
                    throw new InvalidArgumentException('Duplicate dashboard provider: ' . $identifier);
                }

                $providers[$identifier] = $provider;
            }
        }

        $this->providers = $providers;
    }
}
