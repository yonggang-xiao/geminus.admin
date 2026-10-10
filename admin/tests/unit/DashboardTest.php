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

        $progress         = $base + ['max' => 10];
        $progress['type'] = 'progress';

        yield 'progress negative total' => [array_replace($progress, ['max' => -1])];

        yield 'progress fractional total' => [array_replace($progress, ['max' => 1.5])];

        yield 'progress text total' => [array_replace($progress, ['max' => '10'])];

        yield 'progress negative count' => [array_replace($progress, ['value' => -1])];

        yield 'progress boolean count' => [array_replace($progress, ['value' => true])];

        yield 'progress exceeds total' => [array_replace($progress, ['value' => 11])];

        yield 'progress positive value with zero total' => [array_replace($progress, ['max' => 0])];

        yield 'progress arbitrary color' => [$progress + ['tone' => 'red;background:url(evil)']];

        yield 'progress null color' => [$progress + ['tone' => null]];

        yield 'progress raw icon' => [$progress + ['icon' => '<svg>']];

        yield 'progress layout override' => [$progress + ['width' => 'full']];
        $statusList = array_replace($list, ['type' => 'status-list']);

        yield 'status missing label' => [array_replace($statusList, ['rows' => [$row]])];

        yield 'status raw label' => [array_replace($statusList, ['rows' => [$row + ['status' => '<b>Ready</b>']]])];

        yield 'status arbitrary color' => [array_replace($statusList, ['rows' => [$row + ['status' => 'Admin.dashboard', 'tone' => '#ff0000']]])];

        yield 'status boolean color' => [array_replace($statusList, ['rows' => [$row + ['status' => 'Admin.dashboard', 'tone' => true]]])];

        yield 'status raw icon' => [array_replace($statusList, ['rows' => [$row + ['status' => 'Admin.dashboard', 'icon' => '<svg>']]])];

        yield 'status private field' => [array_replace($statusList, ['rows' => [$row + ['status' => 'Admin.dashboard', 'body' => 'Private']]])];

        yield 'status six rows' => [array_replace($statusList, ['rows' => array_fill(0, 6, $row + ['status' => 'Admin.dashboard'])])];

        yield 'ordinary list status override' => [array_replace($list, ['rows' => [$row + ['status' => 'Admin.dashboard']]])];

        yield 'ordinary list tone override' => [array_replace($list, ['rows' => [$row + ['tone' => 'success']]])];

        yield 'ordinary list icon override' => [array_replace($list, ['rows' => [$row + ['icon' => 'check']]])];

        yield 'progress more link override' => [$progress + ['moreLink' => ['route' => 'admin/dashboard', 'label' => 'Dashboard.viewAll']]];

        yield 'status item link override' => [$statusList + ['link' => ['route' => 'admin/dashboard']]];
    }

    public function testProgressAndStatusListContractsPreserveControlledData(): void
    {
        $items = [
            ['id' => 'progress', 'type' => 'progress', 'title' => 'Admin.dashboard', 'order' => 1, 'value' => 0, 'max' => 0, 'description' => 'Admin.dashboard', 'tone' => 'success', 'icon' => 'check', 'link' => ['route' => 'admin/dashboard']],
            ['id' => 'states', 'type' => 'status-list', 'title' => 'Admin.dashboard', 'order' => 2, 'emptyLabel' => 'Dashboard.empty', 'icon' => 'list-check', 'rows' => [
                ['title' => '<Record>', 'status' => 'Dashboard.unavailable', 'tone' => 'danger', 'icon' => 'alert-triangle', 'time' => '2026-10-10T01:02:03Z', 'link' => ['route' => 'admin/dashboard']],
            ], 'moreLink' => ['route' => 'admin/dashboard', 'label' => 'Dashboard.viewAll']],
        ];
        $validated = (new DashboardItems(new DashboardLinks(service('routes'))))->validate($items, 'example', 'zh-Hant');
        $this->assertSame(['example:progress', 'example:states'], array_column($validated, 'key'));
        $this->assertSame(0, $validated[0]['max']);
        $this->assertSame('/zh-Hant/admin/dashboard', $validated[0]['link']['url']);
        $this->assertSame('<Record>', $validated[1]['rows'][0]['title']);
        $this->assertSame('Dashboard.unavailable', $validated[1]['rows'][0]['status']);
        $this->assertSame('/zh-Hant/admin/dashboard', $validated[1]['rows'][0]['link']['url']);
        $this->assertSame('/zh-Hant/admin/dashboard', $validated[1]['moreLink']['url']);
        $items[1]['rows'] = array_fill(0, 5, $items[1]['rows'][0]);
        $validated        = (new DashboardItems(new DashboardLinks(service('routes'))))->validate($items, 'example', 'zh-Hant');
        $this->assertCount(5, $validated[1]['rows']);
        $this->assertSame('/zh-Hant/admin/dashboard', $validated[1]['rows'][4]['link']['url']);
    }

    public function testComponentSemanticTonesHaveFixedTablerColors(): void
    {
        foreach (['default' => 'secondary', 'success' => 'green', 'warning' => 'yellow', 'danger' => 'red', 'info' => 'azure'] as $tone => $color) {
            $items = [
                ['id' => 'progress', 'type' => 'progress', 'title' => 'Admin.dashboard', 'order' => 0, 'value' => 1, 'max' => 2, 'description' => 'Dashboard.usersScope', 'tone' => $tone],
                ['id' => 'states', 'type' => 'status-list', 'title' => 'Admin.dashboard', 'order' => 1, 'emptyLabel' => 'Dashboard.empty', 'rows' => [
                    ['title' => 'Record', 'status' => 'Dashboard.unavailable', 'tone' => $tone, 'link' => ['route' => 'admin/dashboard']],
                ]],
            ];
            $validated      = (new DashboardItems(new DashboardLinks(service('routes'))))->validate($items, 'example', 'en');
            $cell           = new DashboardCell();
            $cell->sections = [['id' => 'example', 'label' => 'Admin.dashboard', 'unavailable' => false, 'items' => $validated]];
            $cell->mount();
            $this->assertSame($color, $cell->sections[0]['panels'][0]['color']);
            $this->assertSame($color, $cell->sections[0]['panels'][1]['rows'][0]['color']);
            $this->assertSame('50%', $cell->sections[0]['panels'][0]['displayPercentage']);
        }
    }

    #[DataProvider('provideCellRendersProgressAndStatusList')]
    public function testCellRendersProgressAndStatusList(int $value, int $max, ?string $percentage, string $displayValue, string $displayMax, ?string $displayPercentage): void
    {
        foreach (['en', 'zh-Hans', 'zh-Hant'] as $locale) {
            $items = [
                ['id' => 'progress', 'type' => 'progress', 'title' => 'Admin.dashboard', 'order' => 0, 'value' => $value, 'max' => $max, 'description' => 'Dashboard.usersScope', 'tone' => 'success', 'icon' => 'check', 'link' => ['route' => 'admin/dashboard']],
                ['id' => 'states', 'type' => 'status-list', 'title' => 'Dashboard.usersRecent', 'order' => 1, 'emptyLabel' => 'Dashboard.usersEmpty', 'icon' => 'list-check', 'rows' => [
                    ['title' => '<unsafe>', 'status' => 'Dashboard.unavailable', 'tone' => 'danger', 'icon' => 'alert-triangle', 'time' => '2026-10-10T01:02:03Z', 'link' => ['route' => 'admin/dashboard']],
                    ['title' => 'Default state', 'status' => 'Dashboard.empty', 'link' => ['route' => 'admin/dashboard']],
                ], 'moreLink' => ['route' => 'admin/dashboard', 'label' => 'Dashboard.viewAll']],
                ['id' => 'empty-states', 'type' => 'status-list', 'title' => 'Dashboard.usersRecent', 'order' => 2, 'emptyLabel' => 'Dashboard.usersEmpty', 'rows' => []],
            ];
            $validated = (new DashboardItems(new DashboardLinks(service('routes'))))->validate($items, 'example', $locale);
            $html      = view_cell('Geminus\Admin\Cells\DashboardCell', ['sections' => [['id' => 'example', 'label' => 'Admin.dashboard', 'unavailable' => false, 'items' => $validated]], 'locale' => $locale, 'timezone' => 'Asia/Shanghai']);
            $document  = new DOMDocument();
            @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath         = new DOMXPath($document);
            $bars          = $xpath->query('//*[@data-dashboard-item="example:progress"]//*[@role="progressbar"]');
            $expectedCount = $locale === 'en' ? 'Completed ' . $displayValue . ' of ' . $displayMax : '已完成 ' . $displayValue . ' / ' . $displayMax;
            $this->assertSame($expectedCount, $xpath->query('//*[@data-dashboard-item="example:progress"]//div[contains(@class,"d-flex")]/span[1]')->item(0)->textContent);
            $this->assertNotSame('Dashboard.progressEmpty', lang('Dashboard.progressEmpty', [], $locale));
            if ($percentage === null) {
                $this->assertCount(0, $bars);
                $expectedEmpty = ['en' => 'No items to track.', 'zh-Hans' => '暂无可统计的进度。', 'zh-Hant' => '暫無可統計的進度。'];
                $this->assertStringContainsString($expectedEmpty[$locale], $html);
            } else {
                $this->assertCount(1, $bars);
                $this->assertSame((string) $value, $bars->item(0)->getAttribute('aria-valuenow'));
                $this->assertSame((string) $max, $bars->item(0)->getAttribute('aria-valuemax'));
                $this->assertSame('width: ' . $percentage . '%', $bars->item(0)->getAttribute('style'));
                $this->assertStringContainsString('bg-green', $bars->item(0)->getAttribute('class'));
                $this->assertSame(lang('Admin.dashboard', [], $locale), $bars->item(0)->getAttribute('aria-label'));
                $this->assertSame($expectedCount, $bars->item(0)->getAttribute('aria-valuetext'));
                $this->assertSame($displayPercentage, $xpath->query('//*[@data-dashboard-item="example:progress"]//span[@class="ms-auto"]')->item(0)->textContent);
            }
            $this->assertCount(1, $xpath->query('//*[@data-dashboard-item="example:progress"]//h4/i[contains(@class,"ti-check") and @aria-hidden="true"]'));
            $this->assertCount(1, $xpath->query('//*[@data-dashboard-item="example:states"]//h4/i[contains(@class,"ti-list-check") and @aria-hidden="true"]'));
            $moreLink = $xpath->query('//*[@data-dashboard-item="example:states"]//div[contains(@class,"card-actions")]/a')->item(0);
            $this->assertSame('/' . $locale . '/admin/dashboard', $moreLink->getAttribute('href'));
            $this->assertStringContainsString(lang('Dashboard.viewAll', [], $locale), $moreLink->textContent);
            $this->assertSame('/' . $locale . '/admin/dashboard', $xpath->query('//*[@data-dashboard-item="example:progress"]//a')->item(0)->getAttribute('href'));
            $this->assertSame('<unsafe>', $xpath->query('//*[@data-dashboard-item="example:states"]//li/a | //*[@data-dashboard-item="example:states"]//li//a')->item(0)->textContent);
            $this->assertSame(lang('Dashboard.unavailable', [], $locale), $xpath->query('//*[@data-dashboard-item="example:states"]//span[contains(@class,"badge")]/span')->item(0)->textContent);
            $this->assertCount(1, $xpath->query('//*[@data-dashboard-item="example:states"]//span[contains(@class,"bg-red-lt")]'));
            $this->assertCount(1, $xpath->query('//*[@data-dashboard-item="example:states"]//span[contains(@class,"bg-secondary-lt")]'));
            $this->assertCount(1, $xpath->query('//*[@data-dashboard-item="example:states"]//i[contains(@class,"ti-alert-triangle") and @aria-hidden="true"]'));
            $this->assertSame('2026-10-10 09:02:03', $xpath->query('//time')->item(0)->textContent);
            $this->assertSame(lang('Dashboard.usersEmpty', [], $locale), $xpath->query('//*[@data-dashboard-item="example:empty-states"]//*[contains(@class,"empty-title")]')->item(0)->textContent);
            $this->assertCount(0, $xpath->query('//unsafe'));
        }
    }

    public static function provideCellRendersProgressAndStatusList(): iterable
    {
        yield 'zero total' => [0, 0, null, '0', '0', null];

        yield 'not started' => [0, 10, '0.0', '0', '10', '0%'];

        yield 'partial' => [1, 3, '33.3', '1', '3', '33.3%'];

        yield 'complete' => [10, 10, '100.0', '10', '10', '100%'];

        $largest       = PHP_INT_SIZE === 8 ? '9,223,372,036,854,775,807' : '2,147,483,647';
        $almostLargest = PHP_INT_SIZE === 8 ? '9,223,372,036,854,775,806' : '2,147,483,646';

        yield 'large counts' => [PHP_INT_MAX, PHP_INT_MAX, '100.0', $largest, $largest, '100%'];

        yield 'almost complete' => [9999, 10000, '99.9', '9,999', '10,000', '99.9%'];

        yield 'almost complete large counts' => [PHP_INT_MAX - 1, PHP_INT_MAX, '99.9', $almostLargest, $largest, '99.9%'];

        yield 'started tiny fraction' => [1, 10000, '0.1', '1', '10,000', '0.1%'];
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
            $document = new DOMDocument();
            @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
            $metricLink = (new DOMXPath($document))->query('//*[@data-dashboard-item="healthy:count"]//a')->item(0);
            $this->assertSame('/' . $locale . '/admin/dashboard', $metricLink->getAttribute('href'));
            $this->assertSame(lang('Dashboard.viewList', [], $locale) . ': ' . lang('Announcements.title', [], $locale) . ': ' . lang('Admin.dashboard', [], $locale), $metricLink->getAttribute('aria-label'));
            $this->assertCount(1, (new DOMXPath($document))->query('//section[@aria-labelledby="dashboard-broken"]'));
            $this->assertCount(0, (new DOMXPath($document))->query('//section[@aria-labelledby="dashboard-broken"]//*[@data-dashboard-item]'));
        }
    }

    public function testCellGroupsMetricsAboveModulesAndRendersShortcutsWithoutCards(): void
    {
        $sections = [
            ['id' => 'mixed', 'label' => 'Admin.users', 'unavailable' => false, 'items' => [
                $this->metric() + ['key' => 'mixed:count'],
                ['id' => 'create', 'key' => 'mixed:create', 'type' => 'shortcut', 'title' => 'Admin.createUser', 'order' => 1, 'link' => ['url' => '/en/admin/users/create'], 'icon' => 'plus'],
                ['id' => 'recent', 'key' => 'mixed:recent', 'type' => 'list', 'title' => 'Dashboard.usersRecent', 'order' => 2, 'rows' => [['title' => '<unsafe>', 'link' => ['url' => '/en/admin/users?q=unsafe'], 'time' => '2026-10-10T01:02:03Z']], 'emptyLabel' => 'Dashboard.usersEmpty', 'moreLink' => ['url' => '/en/admin/users', 'label' => 'Dashboard.viewAll']],
            ]],
            ['id' => 'metric-only', 'label' => 'Admin.mailDeliveries', 'unavailable' => false, 'items' => [$this->metric() + ['key' => 'metric-only:count']]],
            ['id' => 'shortcut-only', 'label' => 'Admin.users', 'unavailable' => false, 'items' => [
                ['id' => 'create', 'key' => 'shortcut-only:create', 'type' => 'shortcut', 'title' => 'Admin.createUser', 'order' => 0, 'link' => ['url' => '/en/admin/users/create']],
            ]],
        ];
        $html     = view_cell('Geminus\Admin\Cells\DashboardCell', ['sections' => $sections, 'locale' => 'en', 'timezone' => 'Asia/Shanghai']);
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($document);
        $this->assertCount(2, $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " card-sm ")]'));
        $this->assertCount(1, $xpath->query('//*[@data-dashboard-item="metric-only:count"]//span[contains(@class,"invisible") and @aria-hidden="true"]'));
        $this->assertCount(0, $xpath->query('//*[@data-dashboard-item="metric-only:count"]//a | //*[@data-dashboard-item="metric-only:count"]//*[@tabindex]'));
        $this->assertCount(0, $xpath->query('//section//*[@data-dashboard-item="mixed:count"]'));
        $this->assertCount(1, $xpath->query('//section[@aria-labelledby="dashboard-mixed"]//a[@data-dashboard-item="mixed:create" and @href="/en/admin/users/create"]'));
        $this->assertCount(0, $xpath->query('//a[@data-dashboard-item="mixed:create"]/ancestor::*[contains(concat(" ", normalize-space(@class), " "), " card ")]'));
        $this->assertCount(1, $xpath->query('//section[@aria-labelledby="dashboard-shortcut-only"]//a[@data-dashboard-item="shortcut-only:create"]'));
        $this->assertCount(1, $xpath->query('//section[@aria-labelledby="dashboard-shortcut-only"]//div[@class="visually-hidden"]/h3[@id="dashboard-shortcut-only"]'));
        $this->assertCount(1, $xpath->query('//section[@aria-labelledby="dashboard-mixed"]//div[@class="col"]/h3[@id="dashboard-mixed"]'));
        $this->assertCount(0, $xpath->query('//section[@aria-labelledby="dashboard-metric-only"]'));
        $this->assertCount(1, $xpath->query('//*[@data-dashboard-item="mixed:recent"]/*[contains(@class,"card-header")]//a[@href="/en/admin/users"]'));
        $this->assertSame('<unsafe>', $xpath->query('//*[@data-dashboard-item="mixed:recent"]//li/a')->item(0)->textContent);
        $this->assertSame('2026-10-10 09:02:03', $xpath->query('//time')->item(0)->textContent);
        $this->assertSame('2026-10-10T01:02:03Z', $xpath->query('//time')->item(0)->getAttribute('datetime'));
        $this->assertLessThan(strpos($html, 'dashboard-mixed'), strpos($html, 'metric-only:count'));
        $this->assertLessThan(strpos($html, 'dashboard-mixed'), strpos($html, 'dashboard-shortcut-only'));
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
