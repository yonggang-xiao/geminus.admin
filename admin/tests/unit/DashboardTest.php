<?php

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Router\RouteCollection;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use Geminus\Admin\Cells\DashboardCell;
use Geminus\Admin\Config\Dashboard;
use Geminus\Admin\Config\Services;
use Geminus\Admin\Libraries\Dashboard\Dashboard as DashboardLibrary;
use Geminus\Admin\Libraries\Dashboard\DashboardItems;
use Geminus\Admin\Libraries\Dashboard\DashboardLinks;
use Geminus\Admin\Libraries\Dashboard\DashboardProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class DashboardTest extends CIUnitTestCase
{
    public function testNumericRegistrarKeysRemainStable(): void
    {
        $original  = BaseConfig::$registrars;
        $registrar = new class () {
            public static function Dashboard(): array
            {
                return ['providers' => ['123' => ['service' => 'example', 'label' => 'Admin.dashboard', 'permissions' => [], 'order' => 1]]];
            }
        };
        BaseConfig::$registrars = [$registrar];

        try {
            $registrations = (new Dashboard())->providers;
            $this->assertArrayHasKey(123, $registrations);
            $this->assertArrayNotHasKey(0, $registrations);
            $provider = $this->createMock(DashboardProvider::class);
            $provider->expects($this->once())->method('items')->willReturn([$this->metric()]);
            $sections = (new DashboardLibrary($registrations, [123 => $provider], new DashboardItems(new DashboardLinks(service('routes'))), service('logger')))->sections(new User(), 'en');
            $this->assertSame('123', $sections[0]['id']);
            $this->assertSame('123:count', $sections[0]['items'][0]['key']);
            BaseConfig::$registrars = [$registrar, $registrar];
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Duplicate dashboard provider: 123');
            new Dashboard();
        } finally {
            BaseConfig::$registrars = $original;
        }
    }

    public function testNoPermissionDoesNotCallOrExposeProvider(): void
    {
        $provider = $this->createMock(DashboardProvider::class);
        $provider->expects($this->never())->method('items');
        $viewer = $this->createMock(User::class);
        $viewer->expects($this->once())->method('can')->with('example.read', 'example.manage')->willReturn(false);
        $registration                = $this->registration();
        $registration['permissions'] = ['example.read', 'example.manage'];
        $dashboard                   = new DashboardLibrary(['secret' => $registration], ['secret' => $provider], new DashboardItems(new DashboardLinks(service('routes'))), service('logger'));
        $this->assertSame([], $dashboard->sections($viewer, 'en'));
    }

    public function testModuleFailureIsAtomicLoggedAndDoesNotStopOtherModules(): void
    {
        $broken = $this->createMock(DashboardProvider::class);
        $broken->expects($this->once())->method('items')->willThrowException(new RuntimeException('Private SQL diagnosis'));
        $healthy = $this->createMock(DashboardProvider::class);
        $healthy->expects($this->once())->method('items')->willReturn([$this->metric()]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with($this->stringContains('{provider}'), $this->callback(static fn (array $context): bool => $context['provider'] === 'alpha' && $context['exception'] instanceof RuntimeException));
        $dashboard = new DashboardLibrary(['zeta' => $this->registration(), 'alpha' => $this->registration()], ['alpha' => $broken, 'zeta' => $healthy], new DashboardItems(new DashboardLinks(service('routes'))), $logger);
        $sections  = $dashboard->sections(new User(), 'en');
        $this->assertSame(['alpha', 'zeta'], array_column($sections, 'id'));
        $this->assertTrue($sections[0]['unavailable']);
        $this->assertSame([], $sections[0]['items']);
        $this->assertFalse($sections[1]['unavailable']);
        $html = view_cell('Geminus\Admin\Cells\DashboardCell', ['sections' => $sections, 'locale' => 'en', 'timezone' => 'UTC']);
        $this->assertStringContainsString('Temporarily unavailable.', $html);
        $this->assertStringNotContainsString('Private SQL diagnosis', $html);
        $this->assertStringContainsString('data-dashboard-item', $html);
    }

    #[DataProvider('provideInvalidItemsDiscardWholeModule')]
    public function testInvalidItemsDiscardWholeModule(array $invalid): void
    {
        $provider = $this->createMock(DashboardProvider::class);
        $provider->expects($this->once())->method('items')->willReturn([$this->metric(), $invalid]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');
        $sections = (new DashboardLibrary(['example' => $this->registration()], ['example' => $provider], new DashboardItems(new DashboardLinks(service('routes'))), $logger))->sections(new User(), 'en');
        $this->assertTrue($sections[0]['unavailable']);
        $this->assertSame([], $sections[0]['items']);
    }

    public static function provideInvalidItemsDiscardWholeModule(): iterable
    {
        $base = ['id' => 'invalid', 'type' => 'metric', 'title' => 'Admin.dashboard', 'order' => 0, 'value' => 1, 'description' => 'Admin.dashboard'];

        yield 'duplicate' => [array_replace($base, ['id' => 'count'])];

        yield 'negative' => [array_replace($base, ['value' => -1])];

        yield 'fractional count' => [array_replace($base, ['value' => 1.5])];

        yield 'formatted count' => [array_replace($base, ['value' => '1,000'])];

        yield 'unsupported type' => [array_replace($base, ['type' => 'html'])];

        yield 'raw label' => [array_replace($base, ['title' => '<b>label</b>'])];

        yield 'unknown field' => [$base + ['template' => 'unsafe.php']];

        yield 'missing description' => [array_diff_key($base, ['description' => true])];

        yield 'invalid order' => [array_replace($base, ['order' => '1'])];

        yield 'invalid identifier' => [array_replace($base, ['id' => 'Bad ID'])];
        $list = ['id' => 'recent', 'type' => 'list', 'title' => 'Admin.dashboard', 'order' => 0, 'emptyLabel' => 'Admin.dashboard', 'rows' => []];

        yield 'missing more label' => [$list + ['moreLink' => ['route' => 'admin/dashboard']]];
        $row = ['title' => 'Record', 'link' => ['route' => 'admin/dashboard']];

        yield 'six rows' => [array_replace($list, ['rows' => array_fill(0, 6, $row)])];

        yield 'row extra fields' => [array_replace($list, ['rows' => [$row + ['body' => 'Private record']]])];

        yield 'non UTC date' => [array_replace($list, ['rows' => [$row + ['time' => '2026-10-10T01:00:00+08:00']]])];

        yield 'impossible date' => [array_replace($list, ['rows' => [$row + ['time' => '2026-02-30T01:00:00Z']]])];

        yield 'external URL' => [$base + ['link' => 'https://example.com']];

        yield 'icon HTML' => [['id' => 'create', 'type' => 'shortcut', 'title' => 'Admin.dashboard', 'order' => 1, 'link' => ['route' => 'admin/dashboard'], 'icon' => '<svg>']];
    }

    #[DataProvider('provideLinksRejectInvalidRoutesAndArguments')]
    public function testLinksRejectInvalidRoutesAndArguments(array $link): void
    {
        $routes = service('routes');
        $routes->loadRoutes();
        $this->expectException(InvalidArgumentException::class);
        (new DashboardLinks($routes))->resolve($link, 'en');
    }

    public static function provideLinksRejectInvalidRoutesAndArguments(): iterable
    {
        yield 'external' => [['route' => 'https://evil.example']];

        yield 'not named' => [['route' => 'Modules\\Announcements\\Controllers\\Announcements::index']];

        yield 'missing' => [['route' => 'admin/missing']];

        yield 'write operation' => [['route' => 'admin/announcements/publish', 'arguments' => [1]]];

        yield 'missing argument' => [['route' => 'admin/announcements/show']];

        yield 'wrong argument' => [['route' => 'admin/announcements/show', 'arguments' => ['abc']]];

        yield 'extra argument' => [['route' => 'admin/announcements', 'arguments' => [1]]];

        yield 'locale override' => [['route' => 'admin/announcements/show', 'arguments' => [1, 'en']]];

        yield 'nested query' => [['route' => 'admin/announcements', 'query' => ['status' => ['draft']]]];

        yield 'unknown link field' => [['route' => 'admin/announcements', 'url' => 'https://evil.example']];
    }

    public function testLinksEncodeParametersQueriesAndExplicitLocale(): void
    {
        $routes = new RouteCollection(service('locator'), config('Modules'), config('Routing'));
        $routes->get('{locale}/admin/example/(:segment)', 'Example::show/$1', ['as' => 'admin/example']);
        $link = (new DashboardLinks($routes))->resolve(['route' => 'admin/example', 'arguments' => ['标题 ?&#%'], 'query' => ['search' => 'a&b=中文', 'status' => 'draft']], 'zh-Hans');
        $this->assertStringStartsWith('/zh-Hans/admin/example/' . rawurlencode('标题 ?&#%') . '?', $link['url']);
        parse_str(parse_url($link['url'], PHP_URL_QUERY), $query);
        $this->assertSame(['search' => 'a&b=中文', 'status' => 'draft'], $query);
    }

    #[DataProvider('provideInvalidRegistrationsHaveConfigurationDiagnostics')]
    public function testInvalidRegistrationsHaveConfigurationDiagnostics(array $registration): void
    {
        $this->expectException(InvalidArgumentException::class);
        DashboardLibrary::validateRegistrations(['example' => $registration]);
    }

    public static function provideInvalidRegistrationsHaveConfigurationDiagnostics(): iterable
    {
        $base = ['service' => 'example', 'label' => 'Admin.dashboard', 'permissions' => [], 'order' => 1];

        yield 'service uppercase' => [array_replace($base, ['service' => 'Example'])];

        yield 'service symbols' => [array_replace($base, ['service' => '../example'])];

        yield 'permission wildcard' => [array_replace($base, ['permissions' => ['example.*']])];

        yield 'permission three parts' => [array_replace($base, ['permissions' => ['example.read.all']])];

        yield 'invalid order' => [array_replace($base, ['order' => '1'])];

        yield 'unknown field' => [$base + ['callback' => 'unsafe']];
    }

    public function testInvalidProviderInstanceIsNotBusinessUnavailableState(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid dashboard provider instance: example');
        new DashboardLibrary(['example' => $this->registration()], ['example' => new stdClass()], new DashboardItems(new DashboardLinks(service('routes'))), service('logger'));
    }

    public function testServicesRejectUnknownProviderService(): void
    {
        $config            = config(Dashboard::class);
        $original          = $config->providers;
        $config->providers = ['example' => array_replace($this->registration(), ['service' => 'missingdashboardservice'])];

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Invalid dashboard provider instance: example');
            Services::dashboard();
        } finally {
            $config->providers = $original;
        }
    }

    public function testCellLocalizesCountsAndEmptyStatesForAllLocales(): void
    {
        foreach (['en' => 'No dashboard items available.', 'zh-Hans' => '暂无可显示的仪表盘内容。', 'zh-Hant' => '暫無可顯示的儀表板內容。'] as $locale => $message) {
            $html = view_cell('Geminus\Admin\Cells\DashboardCell', ['sections' => [], 'locale' => $locale, 'timezone' => 'UTC']);
            $this->assertStringContainsString($message, $html);
            $cell           = new DashboardCell();
            $cell->locale   = $locale;
            $cell->sections = [['id' => 'example', 'label' => 'Admin.dashboard', 'unavailable' => false, 'items' => [array_replace($this->metric(), ['value' => 12345])]]];
            $cell->mount();
            $this->assertSame('12,345', $cell->sections[0]['items'][0]['value']);
            $this->assertNotSame('Admin.dashboard', $cell->sections[0]['label']);

            foreach (['Dashboard.empty', 'Dashboard.unavailable', 'Dashboard.viewAll', 'Dashboard.viewList', 'Announcements.dashboardDrafts', 'Announcements.dashboardDraftScope', 'Announcements.dashboardRecentManaged', 'Announcements.dashboardRecentPublished'] as $key) {
                $this->assertNotSame($key, lang($key, [], $locale));
            }
            $sections = [
                ['id' => 'broken', 'label' => 'Admin.dashboard', 'unavailable' => true, 'items' => []],
                ['id' => 'healthy', 'label' => 'Announcements.title', 'unavailable' => false, 'items' => [
                    $this->metric() + ['key' => 'healthy:count', 'link' => ['url' => '/' . $locale . '/admin/dashboard']],
                    ['id' => 'recent', 'key' => 'healthy:recent', 'type' => 'list', 'title' => 'Announcements.dashboardRecentPublished', 'order' => 1, 'rows' => [], 'emptyLabel' => 'Announcements.emptyPublished', 'moreLink' => ['url' => '/' . $locale . '/admin/announcements', 'label' => 'Dashboard.viewAll']],
                ]],
            ];
            $html = view_cell('Geminus\Admin\Cells\DashboardCell', ['sections' => $sections, 'locale' => $locale, 'timezone' => 'UTC']);
            $this->assertStringContainsString(lang('Dashboard.unavailable', [], $locale), $html);
            $this->assertStringContainsString(lang('Dashboard.viewAll', [], $locale), $html);
            $this->assertStringContainsString(lang('Dashboard.viewList', [], $locale), $html);
            $document = new DOMDocument();
            @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
            $this->assertCount(1, (new DOMXPath($document))->query('//section[@aria-labelledby="dashboard-broken"]'));
            $this->assertCount(0, (new DOMXPath($document))->query('//section[@aria-labelledby="dashboard-broken"]//*[@data-dashboard-item]'));
        }
    }

    private function metric(): array
    {
        return ['id' => 'count', 'type' => 'metric', 'title' => 'Admin.dashboard', 'order' => 0, 'value' => 0, 'description' => 'Admin.dashboard'];
    }

    public function testIndependentProvidersAreSortedAndCalledOnce(): void
    {
        $provider = $this->createMock(DashboardProvider::class);
        $provider->expects($this->once())->method('items')->willReturn([
            ['id' => 'second', 'type' => 'metric', 'title' => 'Admin.dashboard', 'order' => 0, 'value' => 0, 'description' => 'Admin.dashboard'],
            ['id' => 'first', 'type' => 'metric', 'title' => 'Admin.dashboard', 'order' => 0, 'value' => 12, 'description' => 'Admin.dashboard'],
        ]);
        $other = $this->createMock(DashboardProvider::class);
        $other->expects($this->once())->method('items')->willReturn([]);
        $registrations = ['zeta' => $this->registration(), 'alpha' => $this->registration()];
        $dashboard     = new DashboardLibrary($registrations, ['zeta' => $provider, 'alpha' => $other], new DashboardItems(new DashboardLinks(service('routes'))), service('logger'));
        $sections      = $dashboard->sections(new User(), 'en');
        $this->assertSame(['zeta'], array_column($sections, 'id'));
        $this->assertSame(['first', 'second'], array_column($sections[0]['items'], 'id'));
        $this->assertSame(0, $sections[0]['items'][1]['value']);
        $this->assertSame([], (new DashboardLibrary([], [], new DashboardItems(new DashboardLinks(service('routes'))), service('logger')))->sections(new User(), 'en'));
    }

    public function testAnnouncementRegistrationAndNonSharedService(): void
    {
        $this->assertArrayHasKey('announcements', (new Dashboard())->providers);
        $this->assertInstanceOf(DashboardLibrary::class, service('dashboard'));
        $this->assertNotSame(service('dashboard'), service('dashboard'));
        $this->assertNotSame(service('announcementdashboardprovider'), service('announcementdashboardprovider'));
    }

    private function registration(): array
    {
        return ['service' => 'example', 'label' => 'Admin.dashboard', 'permissions' => [], 'order' => 1];
    }

    public function testRegistrarConflictsAreNotSilentlyMerged(): void
    {
        $original  = BaseConfig::$registrars;
        $registrar = new class () {
            public static function Dashboard(): array
            {
                return ['providers' => ['duplicate' => ['service' => 'example']]];
            }
        };
        BaseConfig::$registrars = [$registrar, $registrar];

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Duplicate dashboard provider: duplicate');
            new Dashboard();
        } finally {
            BaseConfig::$registrars = $original;
        }
    }
}
