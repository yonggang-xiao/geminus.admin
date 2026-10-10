<?php

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Geminus\Admin\Entities\AdminUser;
use Geminus\Admin\Libraries\Notifications;
use Geminus\Admin\Models\NotificationModel;
use Geminus\Admin\Models\UserModel;
use Modules\Announcements\Controllers\Announcements;
use Modules\Announcements\Libraries\AnnouncementPublication;
use Modules\Announcements\Models\AnnouncementModel;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class NotificationsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;
    private string $originalLocale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLocale = service('language')->getLocale();
    }

    protected function tearDown(): void
    {
        Events::simulate(true);
        service('language')->setLocale($this->originalLocale);
        Services::resetSingle('notifications');
        Services::resetSingle('announcementPublication');
        auth()->logout();
        parent::tearDown();
    }

    public function testDeliveryIsIdempotentAndReadsAreIsolated(): void
    {
        $users  = new UserModel();
        $first  = new AdminUser(['username' => 'notify-first', 'active' => 1]);
        $second = new AdminUser(['username' => 'notify-second', 'active' => 1]);
        $users->save($first);
        $users->save($second);
        $first   = $users->findById($users->where('username', 'notify-first')->first()->id);
        $second  = $users->where('username', 'notify-second')->first();
        $service = new Notifications(new NotificationModel());
        $service->send((int) $first->id, 'example.completed', 42, 'Completed', 'admin/dashboard');
        $service->send((int) $first->id, 'example.completed', 42, 'Duplicate', 'admin/dashboard');
        $unread = $service->unread($first);
        $this->assertCount(1, $unread);
        $this->assertSame('Completed', $unread[0]['title']);
        $this->assertSame([], $service->unread($second));
        $this->assertNull($service->open($second, (int) $unread[0]['id']));
        $this->assertCount(1, $service->unread($first));
        $this->assertNotNull($service->open($first, (int) $unread[0]['id']));
        $this->assertSame([], $service->unread($first));
        $this->assertNotNull($service->open($first, (int) $unread[0]['id']));
        $this->assertNotNull((new NotificationModel())->find($unread[0]['id'])['read_at']);
        $service->send((int) $first->id, 'example.completed', 42, 'Read duplicate', 'admin/dashboard');
        $this->assertSame([], $service->unread($first));
    }

    public function testPublicationEventDeliversOnlyToAuthorizedExistingUsers(): void
    {
        $publisher  = $this->user('publisher', ['announcements.manage']);
        $reader     = $this->user('reader', ['announcements.access']);
        $manager    = $this->user('manager', ['announcements.manage']);
        $roleReader = $this->user('role-reader');
        $roleReader->addGroup('superadmin');
        $outsider = $this->user('outsider');
        $inactive = $this->user('inactive', ['announcements.access'], false);
        $deleted  = $this->user('deleted', ['announcements.access']);
        (new UserModel())->delete($deleted->id);
        $model   = new AnnouncementModel();
        $draftId = (int) $model->insert(['title' => 'Event notice', 'body' => 'Body']);
        $this->assertSame(0, (new NotificationModel())->countAllResults());
        Events::simulate(false);
        $publication = new AnnouncementPublication($model, service('logger'));
        $publication->publish($draftId, (int) $publisher->id);
        $saved = $model->find($draftId);
        $this->assertSame('published', $saved['status']);
        $this->assertNotNull($saved['published_at']);
        $notifications = service('notifications');
        $this->assertSame([], $notifications->unread($publisher));
        $this->assertCount(1, $notifications->unread($reader));
        $this->assertCount(1, $notifications->unread($manager));
        $this->assertCount(1, $notifications->unread($roleReader));
        $this->assertSame([], $notifications->unread($outsider));
        $this->assertCount(1, $notifications->unread($inactive));
        $this->assertSame([], $notifications->unread($deleted));
        $notification = $notifications->unread($reader)[0];
        $this->assertSame('Event notice', $notification['title']);
        $this->assertSame('announcements.published', $notification['source']);
        $this->assertSame([$draftId], json_decode($notification['target_parameters'], true));
        $publication->publish($draftId, (int) $publisher->id);
        Events::trigger('announcements.published', $draftId, (int) $publisher->id);
        $this->assertSame(4, (new NotificationModel())->countAllResults());
        $this->assertSame($saved['published_at'], $model->find($draftId)['published_at']);
        $model->update($draftId, ['title' => 'Edited']);
        $this->assertSame(4, (new NotificationModel())->countAllResults());
    }

    public function testEventFailureDoesNotUndoPublicationOrRepeatTheEvent(): void
    {
        $publisher = $this->user('event-publisher', ['announcements.manage']);
        $this->user('event-reader', ['announcements.access']);
        $model        = new AnnouncementModel();
        $draftId      = (int) $model->insert(['title' => 'Still published', 'body' => 'Body']);
        $failingModel = $this->createMock(NotificationModel::class);
        $failingModel->expects($this->once())->method('deliver')->willThrowException(new RuntimeException('Delivery failed'));
        Services::injectMock('notifications', new Notifications($failingModel));
        Events::simulate(false);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('Announcement {id} was published but its event failed: {exception}', $this->callback(static fn (array $context): bool => $context['id'] === $draftId && $context['exception']->getMessage() === 'Delivery failed'));
        $publication = new AnnouncementPublication($model, $logger);
        $publication->publish($draftId, (int) $publisher->id);
        $this->assertSame('published', $model->find($draftId)['status']);
        $this->assertNotNull($model->find($draftId)['published_at']);
        $publication->publish($draftId, (int) $publisher->id);
        $this->assertSame(0, (new NotificationModel())->countAllResults());
    }

    public function testDraftAndMissingPublicationDoNotEmitAnEvent(): void
    {
        $publisher = $this->user('draft-publisher', ['announcements.manage']);
        $reader    = $this->user('draft-reader', ['announcements.access']);
        $draftId   = (int) (new AnnouncementModel())->insert(['title' => 'Private', 'body' => 'Body']);
        Events::simulate(false);
        Events::trigger('announcements.published', $draftId, (int) $publisher->id);
        (new AnnouncementPublication(new AnnouncementModel(), service('logger')))->publish(999999, (int) $publisher->id);
        $this->assertSame([], service('notifications')->unread($reader));
        $this->assertSame('draft', (new AnnouncementModel())->find($draftId)['status']);
    }

    public function testDashboardEscapesNotificationsAndPlacesBellAfterTheme(): void
    {
        $user = $this->user('navbar-reader', ['announcements.access']);
        auth()->login($user);
        service('notifications')->send((int) $user->id, 'example.completed', 42, '<script>unsafe()</script>', 'admin/dashboard');
        $page = $this->get('/zh-Hans/admin/dashboard');
        $page->assertOK();
        $body = $page->response()->getBody();
        $this->assertStringContainsString('&lt;script&gt;unsafe()&lt;/script&gt;', $body);
        $this->assertStringNotContainsString('<script>unsafe()</script>', $body);
        $document = new DOMDocument();
        @$document->loadHTML($body);
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//header//*[@data-notifications]/preceding-sibling::*[1][.//*[@data-set-theme]]')->length);
        $this->assertSame(0, $xpath->query('//aside//*[@data-set-theme and contains(concat(" ", normalize-space(@class), " "), " btn-icon ")]')->length);
        $this->assertSame(1, $xpath->query('//aside//*[@data-notifications]')->length);
        $this->assertSame(2, $xpath->query('//form[@data-notification-target="/zh-Hans/admin/dashboard"]')->length);
        $this->assertSame(2, $xpath->query('//form[@data-notification-target]/input[@name="' . csrf_token() . '"]')->length);
    }

    public function testOpenRequiresAuthenticationAndCsrfAndCannotReadAnotherUsersNotification(): void
    {
        $owner = $this->user('http-owner');
        service('notifications')->send((int) $owner->id, 'example.completed', 42, 'Private notification', 'admin/dashboard');
        $notificationId = (new NotificationModel())->first()['id'];
        $route          = '/en/admin/notifications/' . $notificationId . '/open';
        $this->post($route, [csrf_token() => csrf_hash()])->assertRedirect();
        auth()->login($this->user('http-other'));
        $this->post($route, [csrf_token() => csrf_hash()])->assertStatus(404);
        $this->assertNull((new NotificationModel())->find($notificationId)['read_at']);
        auth()->logout();
        auth()->login($owner);
        $this->expectException(SecurityException::class);
        $this->post($route);
    }

    public function testOpeningNotificationRedirectsWithLocaleAndRevokedPermissionsHideIt(): void
    {
        $owner = $this->user('http-reader', ['announcements.access']);
        auth()->login($owner);
        $service = service('notifications');
        $service->send((int) $owner->id, 'announcements.published', 42, 'Authorized notice', 'admin/announcements/show', ['announcements.access', 'announcements.manage'], [42]);
        $notificationId = (new NotificationModel())->first()['id'];
        $route          = '/zh-Hans/admin/notifications/' . $notificationId . '/open';
        $this->post($route, [csrf_token() => csrf_hash()])->assertRedirectTo('/zh-Hans/admin/announcements/42');
        $this->assertSame([], $service->unread($owner));
        $this->assertNotNull((new NotificationModel())->find($notificationId)['read_at']);
        $owner->removePermission('announcements.access');
        auth()->logout();
        $owner = (new UserModel())->findById($owner->id);
        auth()->login($owner);
        $service->send((int) $owner->id, 'announcements.published', 43, 'Revoked notice', 'admin/announcements/show', ['announcements.access', 'announcements.manage'], [43]);
        $this->assertSame([], $service->unread($owner));
        $this->get('/zh-Hans/admin/dashboard')->assertDontSee('Revoked notice');
        $notificationId = (new NotificationModel())->where('reference', 43)->first()['id'];
        $this->post('/zh-Hans/admin/notifications/' . $notificationId . '/open', [csrf_token() => csrf_hash()])->assertStatus(404);
        $this->assertNull((new NotificationModel())->find($notificationId)['read_at']);
    }

    public function testAjaxReadReturnsFreshCsrfAndSafeErrorResponse(): void
    {
        $owner = $this->user('ajax-owner');
        auth()->login($owner);
        service('notifications')->send((int) $owner->id, 'example.completed', 42, 'Ajax notification', 'admin/dashboard');
        $notificationId = (new NotificationModel())->first()['id'];
        $result         = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->post('/en/admin/notifications/' . $notificationId . '/open', [csrf_token() => csrf_hash()]);
        $result->assertOK();
        $json = json_decode($result->response()->getBody(), true);
        $this->assertSame('/en/admin/dashboard', $json['data']['target']);
        $this->assertSame(['name' => csrf_token(), 'hash' => csrf_hash()], $json['csrf']);
        $this->assertNotNull((new NotificationModel())->find($notificationId)['read_at']);
        $result = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->post('/en/admin/notifications/999999/open', [csrf_token() => csrf_hash()]);
        $result->assertStatus(404);
        $json = json_decode($result->response()->getBody(), true);
        $this->assertSame([], $json['errors']);
        $this->assertSame(['name' => csrf_token(), 'hash' => csrf_hash()], $json['csrf']);
    }

    public function testStaleTargetsDoNotBreakPagesOrConsumeUnreadNotifications(): void
    {
        $owner = $this->user('stale-owner');
        auth()->login($owner);
        service('notifications')->send((int) $owner->id, 'example.missing', 1, 'Missing target', 'admin/removed/show', [], [1]);
        service('notifications')->send((int) $owner->id, 'example.invalid', 2, 'Invalid target', 'admin/announcements/show');
        $page = $this->get('/en/admin/dashboard');
        $page->assertOK();
        $page->assertDontSee('Missing target');
        $page->assertDontSee('Invalid target');

        foreach ((new NotificationModel())->findAll() as $notification) {
            $this->post('/en/admin/notifications/' . $notification['id'] . '/open', [csrf_token() => csrf_hash()])->assertStatus(404);
            $this->assertNull((new NotificationModel())->find($notification['id'])['read_at']);
        }
    }

    public function testNotificationLabelsPreserveCountPlaceholderInAllLocales(): void
    {
        $owner = $this->user('locale-owner');
        auth()->login($owner);
        service('notifications')->send((int) $owner->id, 'example.completed', 1, 'Notice', 'admin/dashboard');

        foreach (['en', 'zh-Hans', 'zh-Hant'] as $locale) {
            $page = $this->get('/' . $locale . '/admin/dashboard');
            $page->assertOK();
            $document = new DOMDocument();
            @$document->loadHTML($page->response()->getBody());
            $xpath = new DOMXPath($document);
            $this->assertSame(lang('Notifications.title'), $xpath->query('//*[@data-notifications]/button')->item(0)->getAttribute('aria-label'));
            $this->assertStringContainsString('{count}', $xpath->query('//*[@data-notification-unread-label]')->item(0)->getAttribute('data-notification-unread-label'));
            $this->assertSame(lang('Notifications.unreadCount', [1]), $xpath->query('//*[@data-notification-unread-label]')->item(0)->textContent);
        }
    }

    public function testReadFailureReturnsSafeJsonAndPreservesUnread(): void
    {
        $owner = $this->user('read-failure');
        auth()->login($owner);
        service('notifications')->send((int) $owner->id, 'example.completed', 1, 'Notice', 'admin/dashboard');
        $notification = (new NotificationModel())->first();
        $failing      = $this->getMockBuilder(NotificationModel::class)->onlyMethods(['read'])->getMock();
        $failing->expects($this->once())->method('read')->willReturn(false);
        Services::injectMock('notifications', new Notifications($failing));
        $result = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->post('/en/admin/notifications/' . $notification['id'] . '/open', [csrf_token() => csrf_hash()]);
        $result->assertStatus(500);
        $json = json_decode($result->response()->getBody(), true);
        $this->assertSame(lang('Notifications.readFailed'), $json['message']);
        $this->assertSame([], $json['errors']);
        $this->assertSame(['name' => csrf_token(), 'hash' => csrf_hash()], $json['csrf']);
        $this->assertNull((new NotificationModel())->find($notification['id'])['read_at']);
    }

    public function testControllerPublishesThroughEventsAndPreservesSuccessOnDeliveryFailure(): void
    {
        $owner     = $this->user('controller-manager', ['announcements.manage']);
        $recipient = $this->user('controller-recipient', ['announcements.manage']);
        auth()->login($owner);
        $draftId    = (int) (new AnnouncementModel())->insert(['title' => 'Controller publication', 'body' => 'Body']);
        $controller = new Announcements();
        $controller->initController(service('request'), service('response'), service('logger'));
        Events::simulate(false);
        $controller->publish($draftId);
        $this->assertSame('success', session('alert')['type']);
        $this->assertSame([], service('notifications')->unread($owner));
        $this->assertCount(1, service('notifications')->unread($recipient));
        $controller->publish($draftId);
        $this->assertSame(1, (new NotificationModel())->countAllResults());
        $failingModel = $this->createMock(NotificationModel::class);
        $failingModel->expects($this->once())->method('deliver')->willThrowException(new RuntimeException('Injected failure'));
        Services::injectMock('notifications', new Notifications($failingModel));
        $draftId = (int) (new AnnouncementModel())->insert(['title' => 'Failure boundary', 'body' => 'Body']);
        session()->remove('alert');
        $this->assertNull(session('alert'));
        $response = $controller->publish($draftId);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('success', session('alert')['type']);
        $this->assertSame('published', (new AnnouncementModel())->find($draftId)['status']);
    }

    public function testControllerReportsPublicationFailureWithoutNotifying(): void
    {
        $owner = $this->user('failed-controller', ['announcements.manage']);
        auth()->login($owner);
        $draftId     = (int) (new AnnouncementModel())->insert(['title' => 'Failed publication', 'body' => 'Body']);
        $publication = $this->createMock(AnnouncementPublication::class);
        $publication->expects($this->once())->method('publish')->with($draftId, (int) $owner->id)->willThrowException(new RuntimeException('Save failed'));
        Services::injectMock('announcementPublication', $publication);
        $result = $this->post('/en/admin/announcements/' . $draftId . '/publish', [csrf_token() => csrf_hash()]);
        $result->assertRedirectTo('/en/admin/announcements/' . $draftId);
        $this->assertSame('danger', session('alert')['type']);
        $this->assertSame('draft', (new AnnouncementModel())->find($draftId)['status']);
        $this->assertSame([], service('notifications')->unread($owner));
    }

    private function user(string $name, array $permissions = [], bool $active = true): AdminUser
    {
        $users = new UserModel();
        $users->save(new AdminUser(['username' => $name, 'active' => $active ? 1 : 0]));
        $user = $users->findById($users->getInsertID());
        $user->addGroup('user');
        if ($permissions !== []) {
            $user->addPermission(...$permissions);
        }

        return $user;
    }
}
