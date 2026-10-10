<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries\Dashboard;

use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

class Dashboard
{
    public function __construct(
        private readonly array $registrations,
        private readonly array $providers,
        private readonly DashboardItems $items,
        private readonly LoggerInterface $logger,
    ) {
        self::validateRegistrations($registrations);

        foreach ($registrations as $identifier => $registration) {
            if (! ($providers[$identifier] ?? null) instanceof DashboardProvider) {
                throw new InvalidArgumentException('Invalid dashboard provider instance: ' . $identifier);
            }
        }
    }

    public static function validateRegistrations(array $registrations): void
    {
        foreach ($registrations as $identifier => $registration) {
            DashboardItems::identifier((string) $identifier);
            if (! is_array($registration)) {
                throw new InvalidArgumentException('Invalid dashboard registration: ' . $identifier);
            }
            DashboardItems::fields($registration, ['service', 'label', 'permissions', 'order']);
            DashboardItems::languageKey($registration['label']);
            if (! is_string($registration['service']) || preg_match('/\A[a-z][a-z0-9]*\z/', $registration['service']) !== 1
                                                      || ! is_int($registration['order']) || ! is_array($registration['permissions']) || ! array_is_list($registration['permissions'])) {
                throw new InvalidArgumentException('Invalid dashboard registration: ' . $identifier);
            }

            foreach ($registration['permissions'] as $permission) {
                if (! is_string($permission) || preg_match('/\A[a-z][a-z0-9_-]*\.[a-z][a-z0-9_-]*\z/', $permission) !== 1) {
                    throw new InvalidArgumentException('Dashboard requires concrete two-part permissions: ' . $identifier);
                }
            }
        }
    }

    public function sections(User $viewer, string $locale): array
    {
        $registrations = $this->registrations;
        uksort($registrations, static fn (string $left, string $right): int => ($registrations[$left]['order'] <=> $registrations[$right]['order']) ?: strcmp($left, $right));
        $sections = [];

        foreach ($registrations as $identifier => $registration) {
            $identifier = (string) $identifier;
            if ($registration['permissions'] !== [] && ! $viewer->can(...$registration['permissions'])) {
                continue;
            }

            try {
                $items = $this->items->validate($this->providers[$identifier]->items($viewer), $identifier, $locale);
                if ($items === []) {
                    continue;
                }
                $sections[] = ['id' => $identifier, 'label' => $registration['label'], 'unavailable' => false, 'items' => $items];
            } catch (Throwable $exception) {
                $this->logger->error('Dashboard provider {provider} failed: {exception}', ['provider' => $identifier, 'exception' => $exception]);
                $sections[] = ['id' => $identifier, 'label' => $registration['label'], 'unavailable' => true, 'items' => []];
            }
        }

        return $sections;
    }
}
