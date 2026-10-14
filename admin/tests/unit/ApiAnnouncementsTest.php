<?php

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\I18n\Time;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Settings\Settings;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\Mock\MockCache;
use CodeIgniter\Test\TestResponse;
use CodeIgniter\Throttle\Throttler;
use Config\Auth;
use Config\Services;
use Geminus\Admin\Entities\AdminUser;
use Geminus\Admin\Filters\ApiAccess;
use Geminus\Admin\Filters\ApiIpRateLimit;
use Geminus\Admin\Filters\ApiTokenRateLimit;
use Geminus\Admin\Libraries\Notifications;
use Geminus\Admin\Models\NotificationModel;
use Modules\Announcements\Libraries\AnnouncementApiPublication;
use Modules\Announcements\Libraries\AnnouncementPublication;
use Modules\Announcements\Models\AnnouncementModel;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class ApiAnnouncementsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        Events::simulate(true);
        Services::resetSingle('throttler');
        Services::resetSingle('announcementapipublication');
        Services::resetSingle('notifications');
        Services::resetSingle('logger');
        auth('tokens')->logout();
        auth('session')->logout();
        Services::resetSingle('settings');
        parent::tearDown();
    }

    public function testApiRejectsSessionAndInvalidTokenWithoutWriting(): void
    {
        $user = $this->account();
        auth()->login($user);

        foreach (['', 'Bearer invalid', 'Basic invalid'] as $header) {
            $result = $this->withHeaders(['Authorization' => $header, 'Content-Type' => 'application/json', 'Accept' => 'application/xml'])->withBody('{}')->post('/api/v1/announcements');
            $result->assertStatus(401);
            $this->assertSame(['status' => 401, 'error' => 401, 'messages' => ['error' => 'A valid access token is required.']], json_decode($result->response()->getBody(), true));
            $this->assertApiHeaders($result);
        }
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
        $this->assertSame(0, $this->db->table('operation_audit_logs')->countAllResults());
    }

    public function testShieldTokenAuthenticationLogsRespectConfiguration(): void
    {
        $config   = config('AuthToken');
        $original = $config->recordLoginAttempt;
        $user     = $this->account();
        $allowed  = $user->generateAccessToken('Allowed authentication', ['announcements.manage']);
        $denied   = $user->generateAccessToken('Denied authorization', ['announcements.access']);

        try {
            foreach ([
                [Auth::RECORD_LOGIN_ATTEMPT_NONE, []],
                [Auth::RECORD_LOGIN_ATTEMPT_FAILURE, [0]],
                [Auth::RECORD_LOGIN_ATTEMPT_ALL, [0, 1, 1]],
            ] as [$mode, $expected]) {
                $config->recordLoginAttempt = $mode;
                $this->db->table('auth_token_logins')->emptyTable();
                $this->publishRequest('private-invalid-token')->assertStatus(401);
                $this->publishRequest($allowed->raw_token)->assertStatus(201);
                $this->publishRequest($denied->raw_token)->assertStatus(403);
                $logs = $this->db->table('auth_token_logins')->orderBy('id')->get()->getResultArray();
                $this->assertSame($expected, array_map(static fn (array $log): int => (int) $log['success'], $logs));

                foreach ($logs as $log) {
                    $this->assertSame('access_token', $log['id_type']);
                    $this->assertNotEmpty($log['date']);
                    $this->assertNotEmpty($log['ip_address']);
                }
                if ($mode === Auth::RECORD_LOGIN_ATTEMPT_ALL) {
                    $this->assertSame('Allowed authentication', $logs[1]['identifier']);
                    $this->assertSame('Denied authorization', $logs[2]['identifier']);
                    $this->assertSame((int) $user->id, (int) $logs[1]['user_id']);
                    $this->assertSame((int) $user->id, (int) $logs[2]['user_id']);
                }
                if ($mode !== Auth::RECORD_LOGIN_ATTEMPT_NONE) {
                    $this->assertSame('private-invalid-token', $logs[0]['identifier']);
                }
                $this->assertStringNotContainsString($allowed->raw_token, json_encode($logs));
                $this->assertStringNotContainsString($denied->raw_token, json_encode($logs));
            }
            $this->assertSame(3, (new AnnouncementModel())->countAllResults());
            $this->assertSame(3, $this->db->table('operation_audit_logs')->countAllResults());
        } finally {
            $config->recordLoginAttempt = $original;
        }
    }

    public function testTokenAuthenticationFailureLogsUseShieldIdentifiers(): void
    {
        $user    = $this->account();
        $expired = $user->generateAccessToken('Expired secret', ['announcements.manage'], Time::now('UTC')->subDays(1));
        $token   = $user->generateAccessToken('Banned account token', ['announcements.manage']);

        foreach (['', 'Basic private-invalid-token', 'Bearer private-invalid-token', 'Bearer ' . $expired->raw_token] as $header) {
            $this->withHeaders(['Authorization' => $header, 'Content-Type' => 'application/json'])->withBody('{}')->post('/api/v1/announcements')->assertStatus(401);
        }
        $user->ban();
        $this->publishRequest($token->raw_token)->assertStatus(401);
        $logs = $this->db->table('auth_token_logins')->orderBy('id')->get()->getResultArray();
        $this->assertCount(5, $logs);
        $this->assertSame(['', '', 'private-invalid-token', $expired->raw_token, 'Banned account token'], array_column($logs, 'identifier'));
        $this->assertSame([0, 0, 0, 0, 0], array_map(static fn (array $log): int => (int) $log['success'], $logs));
        $this->assertSame((int) $user->id, (int) $logs[4]['user_id']);
        $this->assertStringNotContainsString($token->raw_token, json_encode($logs));
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
        $this->assertSame(0, $this->db->table('operation_audit_logs')->countAllResults());
    }

    public function testShieldDirectAttemptRecordsSubmittedBearerCredentials(): void
    {
        $token  = $this->account()->generateAccessToken('Direct attempt', ['announcements.manage'], Time::now('UTC')->subDays(1));
        $result = auth('tokens')->attempt(['token' => 'Bearer ' . $token->raw_token]);
        auth('session');
        $this->assertFalse($result->isOK());
        $log = $this->db->table('auth_token_logins')->get()->getRowArray();
        $this->assertSame('Bearer ' . $token->raw_token, $log['identifier']);
        $this->assertSame(0, (int) $log['success']);
    }

    public function testApiRejectsEmptyWildcardAndUnownedScopes(): void
    {
        $user = $this->account();

        foreach ([[], ['*'], ['announcements.*'], ['users.create']] as $scopes) {
            $token = $user->generateAccessToken('Restricted', $scopes);
            $this->publishRequest($token->raw_token, [])->assertStatus(403);
        }
        $user->removePermission('announcements.manage');
        $token = $user->generateAccessToken('Unowned', ['announcements.manage']);
        $this->publishRequest($token->raw_token, [])->assertStatus(403);
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
        $this->assertSame(0, $this->db->table('operation_audit_logs')->countAllResults());
    }

    public function testApiRejectsInvalidJsonAndMediaType(): void
    {
        $token = $this->account()->generateAccessToken('JSON', ['announcements.manage']);

        foreach (['[1]', 'null', '"text"', '{'] as $body) {
            $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token->raw_token, 'Content-Type' => 'application/json'])->withBody($body)->post('/api/v1/announcements');
            $result->assertStatus(400);
            $this->assertApiHeaders($result);
            $this->assertSame(['error' => 'The request body must be a JSON object.'], json_decode($result->response()->getBody(), true)['messages']);
        }
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token->raw_token, 'Content-Type' => 'text/plain'])->withBody('{}')->post('/api/v1/announcements');
        $result->assertStatus(415);
        $this->assertApiHeaders($result);
        $this->assertSame(['error' => 'Content-Type must be application/json.'], json_decode($result->response()->getBody(), true)['messages']);
        $this->assertApiHeaders($result);
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
        $this->assertSame(0, $this->db->table('operation_audit_logs')->countAllResults());
    }

    public function testApiAliasWithoutDeclaredPermissionRejectsAuthenticatedRequests(): void
    {
        $token = $this->account()->generateAccessToken('Missing route permission', ['announcements.manage']);

        try {
            $this->withRoutes([
                ['post', 'api/v1/announcements', '\Modules\Announcements\Controllers\ApiAnnouncements::create', ['filter' => 'api']],
            ]);
            $result = $this->publishRequest($token->raw_token);
            $result->assertStatus(403);
            $this->assertSame(['status' => 403, 'error' => 403, 'messages' => ['error' => 'The API route is not enabled.']], json_decode($result->response()->getBody(), true));
            $this->assertSame(0, (new AnnouncementModel())->countAllResults());
            $this->assertSame(0, $this->db->table('operation_audit_logs')->countAllResults());
        } finally {
            Services::resetSingle('routes');
            Services::resetSingle('router');
        }
    }

    public function testApiPrefixRejectsRoutesWithoutCompletePermissionDeclaration(): void
    {
        $filters = config('Filters');
        $aliases = $filters->aliases;
        $token   = $this->account()->generateAccessToken('Undeclared route', ['announcements.manage']);
        $reached = false;
        $handler = static function () use (&$reached) {
            $reached = true;

            return service('response')->setJSON(['data' => true]);
        };
        $cases = [[], ['filter' => 'api'], ['filter' => 'api:'], ['filter' => 'api:*'], ['filter' => 'api:announcements.manage,users.create'], ['filter' => 'testApiAccess:announcements.manage']];

        $filters->aliases['testApiAccess'] = ApiAccess::class;

        try {
            foreach ($cases as $options) {
                Services::resetSingle('response');
                $this->withRoutes([['post', 'api/v1/unprotected', $handler, $options]]);

                foreach (['', 'Bearer ' . $token->raw_token] as $authorization) {
                    $result = $this->withHeaders(['Authorization' => $authorization, 'Content-Type' => 'application/json'])->withBody('{}')->post('/api/v1/unprotected');
                    $result->assertStatus(403);
                    $this->assertSame(['status' => 403, 'error' => 403, 'messages' => ['error' => 'The API route is not enabled.']], json_decode($result->response()->getBody(), true));
                    $this->assertApiHeaders($result);
                    $this->assertFalse($reached);
                }
            }
            $this->assertSame(0, (new AnnouncementModel())->countAllResults());
            $this->assertSame(0, $this->db->table('operation_audit_logs')->countAllResults());
        } finally {
            $filters->aliases = $aliases;
            Services::resetSingle('routes');
            Services::resetSingle('router');
        }
    }

    public function testNewApiPrefixRouteUsesTokenWithoutSessionOrCsrf(): void
    {
        $token = $this->account()->generateAccessToken('New API route', ['announcements.manage']);

        try {
            $this->withRoutes([
                ['post', 'api/v1/new-announcements', '\Modules\Announcements\Controllers\ApiAnnouncements::create', ['filter' => 'api:announcements.manage']],
            ]);
            $headers   = ['Content-Type' => 'application/json'];
            $body      = json_encode(['title' => 'New route', 'body' => 'Body']);
            $anonymous = $this->withHeaders($headers)->withBody($body)->post('/api/v1/new-announcements');
            $anonymous->assertStatus(401);
            $this->assertApiHeaders($anonymous);
            $result = $this->withHeaders($headers + ['Authorization' => 'Bearer ' . $token->raw_token])->withBody($body)->post('/api/v1/new-announcements');
            $result->assertStatus(201);
            $this->assertApiHeaders($result);
            $this->assertSame(1, (new AnnouncementModel())->countAllResults());
        } finally {
            Services::resetSingle('routes');
            Services::resetSingle('router');
        }
    }

    public function testPathsOutsideApiWildcardRetainCsrfProtection(): void
    {
        $reached = false;
        $handler = static function () use (&$reached) {
            $reached = true;

            return service('response')->setJSON(['data' => true]);
        };

        try {
            foreach (['api/v1', 'api/v1/', 'api/v2/unprotected', 'api/v10/unprotected'] as $path) {
                $this->withRoutes([['post', $path, $handler]]);

                try {
                    $this->withHeaders(['Content-Type' => 'application/json'])->withBody('{}')->post('/' . $path);
                    $this->fail('Writes outside the API wildcard must retain CSRF protection.');
                } catch (SecurityException) {
                    $this->assertFalse($reached);
                }
            }
        } finally {
            Services::resetSingle('routes');
            Services::resetSingle('router');
        }
    }

    public function testPathsOutsideApiWildcardRetainSessionProtection(): void
    {
        $reached = false;
        $handler = static function () use (&$reached) {
            $reached = true;

            return service('response')->setJSON(['data' => true]);
        };

        try {
            foreach (['api/v1', 'api/v1/', 'api/v2/unprotected', 'api/v10/unprotected'] as $path) {
                $this->withRoutes([
                    ['get', $path, $handler],
                    ['get', 'en/login', static fn () => 'Login', ['as' => 'login']],
                ]);
                $this->get('/' . $path)->assertRedirectTo(site_url('en/login'));
                $this->assertFalse($reached);
            }
        } finally {
            Services::resetSingle('routes');
            Services::resetSingle('router');
        }
    }

    public function testAccessFilterOnlyAuthenticatesAndAuthorizes(): void
    {
        $user    = $this->account();
        $token   = $user->generateAccessToken('Access only', ['announcements.manage']);
        $request = new IncomingRequest(config('App'), new URI('https://localhost/api/v1/announcements'), '{', new UserAgent());
        $request->setMethod('POST');
        $request->setHeader('Authorization', 'Bearer ' . $token->raw_token);
        $request->setHeader('Content-Type', 'text/plain');
        Services::injectMock('request', $request);
        $throttler = $this->createMock(Throttler::class);
        $throttler->expects($this->never())->method('check');
        Services::injectMock('throttler', $throttler);

        $this->assertNull((new ApiAccess())->before($request, ['announcements.manage']));
        $this->assertInstanceOf(Session::class, auth()->getAuthenticator());
        $authenticator = auth('tokens')->getAuthenticator();
        auth('session');
        $this->assertSame((int) $user->id, (int) $authenticator->getUser()->id);
        $this->assertSame((int) $token->id, (int) $authenticator->getUser()->currentAccessToken()->id);
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
    }

    public function testFilterStagesRejectInDeclaredOrder(): void
    {
        $user    = $this->account();
        $allowed = $user->generateAccessToken('Allowed stages', ['announcements.manage']);
        $denied  = $user->generateAccessToken('Denied stages', ['announcements.access']);
        $cases   = [
            [false, '', 429, '3'],
            [true, '', 401, ''],
            [true, 'Bearer ' . $denied->raw_token, 403, ''],
            [true, 'Bearer ' . $allowed->raw_token, 429, '3'],
        ];

        foreach ($cases as [$ipAllowed, $authorization, $status, $retryAfter]) {
            Services::resetSingle('response');
            $throttler = $this->createStub(Throttler::class);
            $throttler->method('check')->willReturnCallback(static fn (string $key): bool => $ipAllowed && str_starts_with($key, 'api-ip-'));
            $throttler->method('getTokenTime')->willReturn(3);
            Services::injectMock('throttler', $throttler);
            $result = $this->withHeaders(['Authorization' => $authorization, 'Content-Type' => 'text/plain'])->withBody('{')->post('/api/v1/announcements');
            $result->assertStatus($status);
            $this->assertSame($status, json_decode($result->response()->getBody(), true)['error']);
            $this->assertSame($retryAfter, $result->response()->getHeaderLine('Retry-After'));
            $this->assertApiHeaders($result);
        }
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
        $this->assertSame(0, $this->db->table('operation_audit_logs')->countAllResults());
    }

    public function testIpRateLimitsUseIndependentBuckets(): void
    {
        $throttler = new Throttler(new MockCache());
        $throttler->setTestTime(1000);
        Services::injectMock('throttler', $throttler);
        $first = $this->createStub(RequestInterface::class);
        $first->method('getIPAddress')->willReturn('192.0.2.1');
        $second = $this->createStub(RequestInterface::class);
        $second->method('getIPAddress')->willReturn('192.0.2.2');
        $filter = new ApiIpRateLimit();

        for ($index = 0; $index < 120; $index++) {
            $this->assertNull($filter->before($first));
        }
        $this->assertSame(429, $filter->before($first)->getStatusCode());
        $this->assertNull($filter->before($second));
    }

    public function testTokenRateLimitRequiresTokenAuthenticationNotSession(): void
    {
        $user  = $this->account();
        $token = $user->generateAccessToken('Session cannot substitute', ['announcements.manage']);
        $user->setAccessToken($token);
        auth()->login($user);
        $request   = new IncomingRequest(config('App'), new URI('https://localhost/api/v1/announcements'), '{}', new UserAgent());
        $throttler = $this->createMock(Throttler::class);
        $throttler->expects($this->never())->method('check');
        Services::injectMock('throttler', $throttler);

        $response = (new ApiTokenRateLimit())->before($request);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(['status' => 401, 'error' => 401, 'messages' => ['error' => 'A valid access token is required.']], json_decode($response->getBody(), true));
        $this->assertContains('no-store', array_map('trim', explode(',', $response->getHeaderLine('Cache-Control'))));
        $this->assertSame((int) $user->id, (int) auth()->id());
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
    }

    public function testRateLimitFailuresReturnSafeErrorsWithoutWriting(): void
    {
        $token = $this->account()->generateAccessToken('Rate failure', ['announcements.manage']);

        foreach (['api-ip-', 'api-token-'] as $failedPrefix) {
            $throttler = $this->createStub(Throttler::class);
            $throttler->method('check')->willReturnCallback(static function (string $key) use ($failedPrefix): bool {
                if (str_starts_with($key, $failedPrefix)) {
                    throw new RuntimeException('Private cache connection details');
                }

                return true;
            });
            Services::injectMock('throttler', $throttler);
            $result = $this->publishRequest($token->raw_token);
            $result->assertStatus(500);
            $this->assertSame(['status' => 500, 'error' => 500, 'messages' => ['error' => 'The request could not be completed.']], json_decode($result->response()->getBody(), true));
            $this->assertStringNotContainsString($token->raw_token, $result->response()->getBody());
        }
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
        $this->assertSame(0, $this->db->table('operation_audit_logs')->countAllResults());
    }

    private function account(): AdminUser
    {
        $user  = new AdminUser(['username' => 'api-' . bin2hex(random_bytes(4))]);
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addPermission('announcements.manage');

        return $user;
    }

    public function testEverySuccessfulRequestCreatesANewAnnouncement(): void
    {
        $this->assertFalse($this->db->tableExists('announcement_api_submissions'));
        $user   = $this->account();
        $token  = $user->generateAccessToken('Publish', ['announcements.manage']);
        $input  = ['title' => '  Maintenance  ', 'body' => " Notice\nTomorrow "];
        $result = $this->publishRequest($token->raw_token, $input);
        $result->assertStatus(201);
        $first = json_decode($result->response()->getBody(), true);
        $this->assertSame(['data'], array_keys($first));
        $this->assertSame('published', $first['data']['status']);
        $this->assertMatchesRegularExpression('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/', $first['data']['published_at']);
        $this->assertArrayNotHasKey('body', $first['data']);
        $model = new AnnouncementModel();
        $saved = $model->find($first['data']['id']);
        $this->assertSame((new DateTimeImmutable($saved['published_at'], new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'), $first['data']['published_at']);
        $this->assertSame('Maintenance', $saved['title']);
        $this->assertSame("Notice\nTomorrow", $saved['body']);
        $this->assertSame('published', $saved['status']);
        $model->update($saved['id'], ['title' => 'Edited', 'body' => 'Changed', 'status' => 'draft', 'published_at' => null]);
        $rotated = $user->generateAccessToken('Rotated', ['announcements.manage']);
        $retry   = $this->publishRequest($rotated->raw_token, $input);
        $retry->assertStatus(201);
        $secondId = json_decode($retry->response()->getBody(), true)['data']['id'];
        $this->assertNotSame($first['data']['id'], $secondId);
        $this->assertSame('Maintenance', $model->find($secondId)['title']);
        $this->assertSame("Notice\nTomorrow", $model->find($secondId)['body']);
        $normalized = $this->publishRequest($rotated->raw_token, ['title' => 'Maintenance', 'body' => "Notice\nTomorrow"]);
        $normalized->assertStatus(201);
        $thirdId = json_decode($normalized->response()->getBody(), true)['data']['id'];
        $this->assertNotContains($thirdId, [$first['data']['id'], $secondId]);
        $input['body'] = 'Different';
        $changed       = $this->publishRequest($rotated->raw_token, $input);
        $changed->assertStatus(201);
        $this->assertSame('Different', $model->find(json_decode($changed->response()->getBody(), true)['data']['id'])['body']);
        $this->assertSame(4, $model->countAllResults());
        $this->assertSame('Changed', $model->find($saved['id'])['body']);
        $other = $this->account()->generateAccessToken('Other', ['announcements.manage']);
        $this->withHeaders(['Authorization' => 'Bearer ' . $other->raw_token, 'Content-Type' => 'application/json'])->withBody(json_encode($input))->post('/api/v1/announcements')->assertStatus(201);
        $this->assertSame(5, $model->countAllResults());
    }

    public function testInvalidFieldsNeverCreateAnnouncements(): void
    {
        $token = $this->account()->generateAccessToken('Validate', ['announcements.manage']);
        $valid = ['title' => 'Title', 'body' => 'Body'];

        foreach ([['source_id' => 'obsolete'], ['title' => '  '], ['title' => str_repeat('a', 151)], ['title' => false], ['title' => true], ['title' => 123], ['body' => []], ['body' => ['x']], ['body' => 123], ['body' => " \n\t "], ['body' => str_repeat('a', 2001)], ['status' => 'published'], ['actor_id' => 123]] as $changes) {
            $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token->raw_token, 'Content-Type' => 'application/json'])->withBody(json_encode(array_replace($valid, $changes)))->post('/api/v1/announcements');
            $result->assertStatus(422);
            $this->assertApiHeaders($result);
            $this->assertArrayHasKey(array_key_first($changes), json_decode($result->response()->getBody(), true)['messages']);
        }
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
    }

    public function testAuditUsesTokenActorAndOnlyDeclaredFields(): void
    {
        $actor       = $this->account();
        $sessionUser = $this->account();
        auth()->login($sessionUser);
        $token = $actor->generateAccessToken('Audit', ['announcements.manage']);
        $input = ['title' => 'Audit title', 'body' => 'Sensitive body'];
        $this->withHeaders(['Authorization' => 'Bearer ' . $token->raw_token, 'Content-Type' => 'application/json'])->withBody(json_encode($input))->post('/api/v1/announcements')->assertStatus(201);
        $record = $this->db->table('operation_audit_logs')->where('path', 'api/v1/announcements')->get()->getRowArray();
        $this->assertNotNull($record);
        $this->assertSame((int) $actor->id, (int) $record['actor_id']);
        $this->assertSame('announcement.api.publish', $record['operation']);
        $this->assertSame('announcement', $record['target_type']);
        $this->assertSame('success', $record['result']);
        $fields = json_decode($record['submission'], true)['fields'];
        ksort($fields);
        $this->assertSame(['title' => 'Audit title'], $fields);
        $this->assertStringNotContainsString('Sensitive body', $record['submission']);
        $this->assertStringNotContainsString($token->raw_token, json_encode($record));
        $this->assertNotSame((int) $sessionUser->id, (int) $record['actor_id']);
        $input['body'] = 'Other content';
        $sessionToken  = $sessionUser->generateAccessToken('Separate actor', ['announcements.manage']);
        $this->publishRequest($sessionToken->raw_token, $input)->assertStatus(201);
        $this->publishRequest($token->raw_token, $input)->assertStatus(201);
        $this->assertSame(3, (new AnnouncementModel())->countAllResults());
    }

    private function publishRequest(string $rawToken, array $input = ['title' => 'Title', 'body' => 'Body']): TestResponse
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $rawToken, 'Content-Type' => 'application/json'])->withBody(json_encode($input))->post('/api/v1/announcements');
        $this->assertApiHeaders($result);

        return $result;
    }

    private function assertApiHeaders(TestResponse $result): void
    {
        $this->assertContains('no-store', array_map('trim', explode(',', $result->response()->getHeaderLine('Cache-Control'))));
        $this->assertStringStartsWith('application/json', $result->response()->getHeaderLine('Content-Type'));
        $status = $result->response()->getStatusCode();
        if ($status >= 400) {
            $payload = json_decode($result->response()->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertEqualsCanonicalizing(['status', 'error', 'messages'], array_keys($payload));
            $this->assertSame($status, $payload['status']);
            $this->assertSame($status, $payload['error']);
            $this->assertIsArray($payload['messages']);
        }
    }

    public function testCurrentPermissionsAndProfileReflectDirectGrantChanges(): void
    {
        $user = $this->account();
        auth()->login($user);
        $token = $user->generateAccessToken('Current permissions', ['announcements.manage']);
        $this->publishRequest($token->raw_token)->assertStatus(201);
        $this->get('/en/admin/profile')->assertSee('Effective');
        $otherInstance = auth()->getProvider()->findById($user->id);
        $otherInstance->removePermission('announcements.manage');
        $this->publishRequest($token->raw_token)->assertStatus(403);
        $this->get('/en/admin/profile')->assertSee('Account not authorized');
        $otherInstance->addPermission('announcements.manage');
        $this->publishRequest($token->raw_token)->assertStatus(201);
        $this->get('/en/admin/profile')->assertSee('Effective');
        $this->assertSame(['announcements.manage'], $user->getAccessTokenById($token->id)->scopes);
        $this->assertSame(2, (new AnnouncementModel())->countAllResults());
    }

    public function testCurrentMatrixAndRoleChangesAreNotCached(): void
    {
        $user = $this->account();
        $user->removePermission('announcements.manage');
        $user->addGroup('admin');
        $settings        = new Settings(config('Settings'));
        $matrix          = $settings->get('AuthGroups.matrix');
        $matrix['admin'] = ['announcements.manage'];
        $settings->set('AuthGroups.matrix', $matrix);
        $token = $user->generateAccessToken('Matrix', ['announcements.manage']);
        auth()->login($user);
        $this->publishRequest($token->raw_token)->assertStatus(201);
        $this->get('/en/admin/profile')->assertSee('Effective');
        $matrix['admin'] = [];
        $settings->set('AuthGroups.matrix', $matrix);
        $this->publishRequest($token->raw_token)->assertStatus(403);
        $this->get('/en/admin/profile')->assertSee('Account not authorized');
        $matrix['admin'] = ['announcements.manage'];
        $settings->set('AuthGroups.matrix', $matrix);
        $this->publishRequest($token->raw_token)->assertStatus(201);
        auth()->getProvider()->findById($user->id)->removeGroup('admin');
        $this->publishRequest($token->raw_token)->assertStatus(403);
        $user->addPermission('announcements.manage');
        $this->publishRequest($token->raw_token)->assertStatus(201);
        $this->assertSame(3, (new AnnouncementModel())->countAllResults());
    }

    public function testCatalogRemovalAndDifferentTokensCannotBorrowAuthorization(): void
    {
        $user = $this->account();
        auth()->login($user);
        $allowed = $user->generateAccessToken('Allowed', ['announcements.manage']);
        $denied  = $user->generateAccessToken('Denied', ['announcements.access']);
        $this->publishRequest($allowed->raw_token)->assertStatus(201);
        $user->addPermission('announcements.access');
        $this->publishRequest($denied->raw_token)->assertStatus(403);
        $settings = new Settings(config('Settings'));
        $catalog  = $settings->get('AuthGroups.permissions');
        unset($catalog['announcements.manage']);
        $settings->set('AuthGroups.permissions', $catalog);
        $this->publishRequest($allowed->raw_token)->assertStatus(403);
        $this->get('/en/admin/profile')->assertSee('Permission not registered');
        $this->assertSame(1, (new AnnouncementModel())->countAllResults());
    }

    public function testExpiredRevokedUnusedAndBannedTokensAreUnauthenticated(): void
    {
        $user    = $this->account();
        $expired = $user->generateAccessToken('Expired', ['announcements.manage'], Time::now('UTC')->subDays(1));
        $this->publishRequest($expired->raw_token)->assertStatus(401);
        $token        = $user->generateAccessToken('Revoked', ['announcements.manage']);
        $revokedToken = $token->raw_token;
        $user->revokeAccessToken($token->raw_token);
        $this->publishRequest($token->raw_token)->assertStatus(401);
        $unused = $user->generateAccessToken('Unused', ['announcements.manage']);
        $this->db->table('auth_identities')->where('id', $unused->id)->update(['last_used_at' => Time::now('UTC')->subSeconds(setting('AuthToken.unusedTokenLifetime') + 1)->toDateTimeString()]);
        $this->publishRequest($unused->raw_token)->assertStatus(401);
        $token = $user->generateAccessToken('Banned', ['announcements.manage']);
        $user->ban();
        $this->publishRequest($token->raw_token)->assertStatus(401);
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
        $logs = $this->db->table('auth_token_logins')->orderBy('id')->get()->getResultArray();
        $this->assertSame([$expired->raw_token, $revokedToken, $unused->raw_token, 'Banned'], array_column($logs, 'identifier'));
        $this->assertSame([0, 0, 0, 0], array_map(static fn (array $log): int => (int) $log['success'], $logs));
    }

    public function testIpAndTokenRateLimitsReturnRetryAfterWithoutWriting(): void
    {
        $token = $this->account()->generateAccessToken('Limited', ['announcements.manage']);

        foreach ([false, true] as $ipAllowed) {
            $throttler = $this->createStub(Throttler::class);
            $throttler->method('check')->willReturnOnConsecutiveCalls($ipAllowed, false);
            $throttler->method('getTokenTime')->willReturn(7);
            Services::injectMock('throttler', $throttler);
            $result = $this->publishRequest($token->raw_token);
            $result->assertStatus(429);
            $this->assertSame('7', $result->response()->getHeaderLine('Retry-After'));
            $this->assertSame(['error' => 'Too many requests.'], json_decode($result->response()->getBody(), true)['messages']);
        }
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
        $this->assertSame(0, $this->db->table('operation_audit_logs')->countAllResults());
    }

    public function testRealTokenBucketAllowsSixtyRequestsAndRefillsOnePerSecond(): void
    {
        $user      = $this->account();
        $token     = $user->generateAccessToken('Real limit', ['announcements.manage']);
        $throttler = new Throttler(new MockCache());
        $throttler->setTestTime(1000);
        Services::injectMock('throttler', $throttler);

        for ($index = 0; $index < 60; $index++) {
            $this->publishRequest($token->raw_token)->assertStatus(201);
        }
        $denied = $this->publishRequest($token->raw_token);
        $denied->assertStatus(429);
        $this->assertSame('1', $denied->response()->getHeaderLine('Retry-After'));
        $other = $user->generateAccessToken('Separate bucket', ['announcements.manage']);
        $this->publishRequest($other->raw_token)->assertStatus(201);
        $throttler->setTestTime(1001);
        $this->publishRequest($token->raw_token)->assertStatus(201);
        $this->publishRequest($token->raw_token)->assertStatus(429);
        $this->assertSame(62, (new AnnouncementModel())->countAllResults());
    }

    public function testRealIpBucketLimitsRejectedRequestsWithoutAuditingThem(): void
    {
        $throttler = new Throttler(new MockCache());
        $throttler->setTestTime(1000);
        Services::injectMock('throttler', $throttler);

        for ($index = 0; $index < 120; $index++) {
            $this->publishRequest('invalid')->assertStatus(401);
        }
        $denied = $this->publishRequest('invalid');
        $denied->assertStatus(429);
        $this->assertSame('1', $denied->response()->getHeaderLine('Retry-After'));
        $throttler->setTestTime(1001);
        $this->publishRequest('invalid')->assertStatus(401);
        $this->publishRequest('invalid')->assertStatus(401);
        $this->publishRequest('invalid')->assertStatus(429);
        $this->assertSame(0, $this->db->table('operation_audit_logs')->countAllResults());
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
    }

    public function testRollbackOnPublicationFailureAllowsRetryAndEmitsOnlyAfterCommit(): void
    {
        $user  = $this->account();
        $token = $user->generateAccessToken('Rollback', ['announcements.manage']);
        $model = new class ($this->db) extends AnnouncementModel {
            public bool $fail         = true;
            public array $createdRows = [];

            public function insert($row = null, bool $returnID = true)
            {
                $result              = parent::insert($row, $returnID);
                $this->createdRows[] = $this->find((int) $this->getInsertID());
                if ($this->fail) {
                    $this->db->query('SELECT CAST(? AS INTEGER)', ['invalid']);
                }

                return $result;
            }
        };
        $service = new AnnouncementApiPublication($this->db, $model, new AnnouncementPublication($model, service('logger')), Services::validation(null, false));
        Services::injectMock('announcementapipublication', $service);
        $events   = [];
        $listener = function (int $announcementId, int $publisherId) use (&$events): void {
            $this->assertSame(0, $this->db->transDepth);
            $this->assertSame('published', (new AnnouncementModel())->find($announcementId)['status']);
            $events[] = [$announcementId, $publisherId];
        };
        Events::simulate(false);
        Events::on('announcements.published', $listener);

        try {
            $result = $this->publishRequest($token->raw_token);
            $result->assertStatus(500);
            $this->assertSame(['status' => 500, 'error' => 500, 'messages' => ['error' => 'The request could not be completed.']], json_decode($result->response()->getBody(), true));
            $this->assertSame('published', $model->createdRows[0]['status']);
            $this->assertNotNull($model->createdRows[0]['published_at']);
            $this->assertSame(0, $model->countAllResults());
            $this->assertSame([], $events);
            $model->fail = false;
            $retry       = $this->publishRequest($token->raw_token);
            $retry->assertStatus(201);
            $id = json_decode($retry->response()->getBody(), true)['data']['id'];
            $this->assertSame([[$id, (int) $user->id]], $events);
            $second = $this->publishRequest($token->raw_token);
            $second->assertStatus(201);
            $secondId = json_decode($second->response()->getBody(), true)['data']['id'];
            $this->assertNotSame($id, $secondId);
            $this->assertSame([[$id, (int) $user->id], [$secondId, (int) $user->id]], $events);
            $this->assertSame(2, $model->countAllResults());
        } finally {
            Events::removeListener('announcements.published', $listener);
        }
    }

    public function testRepeatedRequestsNotifyForEachNewAnnouncementWithoutRestoringReadState(): void
    {
        $publisher = $this->account();
        $reader    = $this->account();
        $token     = $publisher->generateAccessToken('Notifications', ['announcements.manage']);
        Events::simulate(false);
        $this->publishRequest($token->raw_token)->assertStatus(201);
        $notifications = service('notifications');
        $this->assertSame([], $notifications->unread($publisher));
        $rows = $notifications->unread($reader);
        $this->assertCount(1, $rows);
        $this->assertNotNull($notifications->open($reader, (int) $rows[0]['id']));
        $readAt = (new NotificationModel())->find($rows[0]['id'])['read_at'];
        $this->assertNotNull($readAt);
        $this->publishRequest($token->raw_token)->assertStatus(201);
        $newRows = $notifications->unread($reader);
        $this->assertCount(1, $newRows);
        $this->assertNotSame((int) $rows[0]['id'], (int) $newRows[0]['id']);
        $this->assertNotSame($rows[0]['reference'], $newRows[0]['reference']);
        $this->assertSame([], $notifications->unread($publisher));
        $this->assertSame(2, (new NotificationModel())->countAllResults());
        $this->assertSame(2, (new AnnouncementModel())->countAllResults());
        $this->assertSame($readAt, (new NotificationModel())->find($rows[0]['id'])['read_at']);
    }

    public function testNotificationFailureDoesNotUndoApiPublication(): void
    {
        $publisher = $this->account();
        $this->account();
        $token             = $publisher->generateAccessToken('Notification failure', ['announcements.manage']);
        $notificationModel = $this->createMock(NotificationModel::class);
        $notificationModel->expects($this->atLeastOnce())->method('deliver')->willThrowException(new RuntimeException('Delivery failed'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('error')->with('Announcement {id} was published but its event failed: {exception}', $this->callback(static fn (array $context): bool => $context['exception']->getMessage() === 'Delivery failed'));
        Services::injectMock('logger', $logger);
        Services::injectMock('notifications', new Notifications($notificationModel));
        Events::simulate(false);
        $this->publishRequest($token->raw_token)->assertStatus(201);
        $this->publishRequest($token->raw_token)->assertStatus(201);
        $this->assertSame(2, (new AnnouncementModel())->countAllResults());
        $this->assertSame(['published', 'published'], array_column((new AnnouncementModel())->findAll(), 'status'));
        $this->assertSame(0, (new NotificationModel())->countAllResults());
    }

    public function testExactFieldLimitsAreAcceptedAndContentFieldsAreRequired(): void
    {
        $token = $this->account()->generateAccessToken('Boundaries', ['announcements.manage']);
        $input = ['title' => '  ' . str_repeat("\u{4E2D}", 150) . '  ', 'body' => " \n" . str_repeat('b', 2000) . "\t "];
        $this->publishRequest($token->raw_token, $input)->assertStatus(201);
        $this->publishRequest($token->raw_token, $input)->assertStatus(201);
        $this->assertSame(2, (new AnnouncementModel())->countAllResults());
        $saved = (new AnnouncementModel())->first();
        $this->assertSame(str_repeat("\u{4E2D}", 150), $saved['title']);
        $this->assertSame(str_repeat('b', 2000), $saved['body']);

        foreach (['title', 'body'] as $field) {
            $missing = $input;
            unset($missing[$field]);
            $result = $this->publishRequest($token->raw_token, $missing);
            $result->assertStatus(422);
            $this->assertArrayHasKey($field, json_decode($result->response()->getBody(), true)['messages']);
        }
    }

    public function testSuperadminCannotUseWildcardAndApiDoesNotDisableBackendCsrf(): void
    {
        $user = $this->account();
        $user->addGroup('superadmin');
        auth()->login($user);
        $legacy = $user->generateAccessToken('Wildcard', ['*']);
        $this->publishRequest($legacy->raw_token)->assertStatus(403);
        $explicit = $user->generateAccessToken('Explicit', ['announcements.manage']);
        $this->publishRequest($explicit->raw_token)->assertStatus(201);
        $this->expectException(SecurityException::class);
        $this->withHeaders([])->withBody('')->post('/en/admin/profile/tokens', ['name' => 'No CSRF', 'expires' => '2030-01-01', 'scopes' => ['announcements.manage']]);
    }

    public function testFailedAuthenticatedRequestsAreAuditedWithoutSensitiveContent(): void
    {
        $user  = $this->account();
        $token = $user->generateAccessToken('Failed audit', ['announcements.manage']);
        $this->publishRequest($token->raw_token, ['title' => '', 'body' => 'Private rejected body'])->assertStatus(422);
        $row = $this->db->table('operation_audit_logs')->where('path', 'api/v1/announcements')->get()->getRowArray();
        $this->assertSame('failed', $row['result']);
        $this->assertSame((int) $user->id, (int) $row['actor_id']);
        $this->assertStringNotContainsString('Private rejected body', $row['submission']);
        $this->assertStringNotContainsString($token->raw_token, json_encode($row));
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());

        $result = $this->publishRequest($token->raw_token, ['title' => 'Unknown field', 'body' => 'Body', 'source_id' => 'leak-marker']);
        $result->assertStatus(422);
        $this->assertArrayHasKey('source_id', json_decode($result->response()->getBody(), true)['messages']);
        $unknown = $this->db->table('operation_audit_logs')->where('path', 'api/v1/announcements')->orderBy('id', 'DESC')->get()->getRowArray();
        $this->assertNotSame($row['id'], $unknown['id']);
        $this->assertSame(['title' => 'Unknown field'], json_decode($unknown['submission'], true)['fields']);
        $this->assertStringNotContainsString('leak-marker', $unknown['submission']);
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
    }

    public function testNulInAnnouncementTextIsAFieldErrorNotRetryableFailure(): void
    {
        $token = $this->account()->generateAccessToken('Invalid text', ['announcements.manage']);

        foreach (['title', 'body'] as $field) {
            $input         = ['title' => 'Title', 'body' => 'Body'];
            $input[$field] = "Text\0content";
            $result        = $this->publishRequest($token->raw_token, $input);
            $result->assertStatus(422);
            $this->assertArrayHasKey($field, json_decode($result->response()->getBody(), true)['messages']);
        }
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
    }

    public function testConcurrentRequestsEachCreateAnAnnouncement(): void
    {
        $actorId = (int) $this->account()->id;

        foreach ([false, true] as $differentContent) {
            $input           = ['title' => 'Concurrent', 'body' => 'Body'];
            $other           = array_replace($input, ['body' => $differentContent ? 'Different' : 'Body']);
            $applicationName = 'api-test-' . bin2hex(random_bytes(6));
            $processes       = [];
            $worker          = <<<'PHP'
                $_SERVER['CI_ENVIRONMENT'] = 'testing';
                require 'vendor/codeigniter4/framework/system/util_bootstrap.php';
                helper('auth');
                $db = db_connect('tests');
                $db->query('SELECT set_config(?, ?, false)', ['application_name', $argv[3]]);
                echo "ready\n";
                fflush(STDOUT);
                fgets(STDIN);
                $result = service('announcementapipublication')->publish((int) $argv[1], json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR));
                echo json_encode(['result' => $result], JSON_THROW_ON_ERROR);
                PHP;
            $this->db->transBegin();
            $this->db->query('LOCK TABLE example_announcements IN SHARE MODE');

            try {
                foreach ([$input, $other] as $payload) {
                    $process = proc_open([PHP_BINARY, '-r', $worker, (string) $actorId, json_encode($payload), $applicationName], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, ROOTPATH);
                    $this->assertIsResource($process);
                    stream_set_timeout($pipes[1], 10);
                    $processes[] = ['process' => $process, 'pipes' => $pipes];
                }

                foreach ($processes as $process) {
                    $this->assertSame("ready\n", fgets($process['pipes'][1]));
                    fwrite($process['pipes'][0], "go\n");
                    fflush($process['pipes'][0]);
                }
                $deadline = microtime(true) + 10;

                do {
                    $waiting = (int) $this->db->query("SELECT count(*) AS total FROM pg_stat_activity WHERE application_name = ? AND wait_event = 'relation'", [$applicationName])->getRow()->total;
                } while ($waiting < 2 && microtime(true) < $deadline);
                $this->assertSame(2, $waiting, 'Both independent requests must overlap before their inserts.');
            } finally {
                $this->db->transRollback();
            }
            $results = [];

            foreach ($processes as $process) {
                fclose($process['pipes'][0]);
                $output = stream_get_contents($process['pipes'][1]);
                $errors = stream_get_contents($process['pipes'][2]);
                fclose($process['pipes'][1]);
                fclose($process['pipes'][2]);
                $this->assertSame(0, proc_close($process['process']), $errors . $output);
                $results[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            }
            $successes = array_values(array_filter($results, static fn (array $result): bool => isset($result['result'])));
            $this->assertCount(2, $successes);
            $this->assertNotSame($successes[0]['result']['data']['id'], $successes[1]['result']['data']['id']);

            foreach ($successes as $index => $success) {
                $saved = (new AnnouncementModel())->find($success['result']['data']['id']);
                $this->assertSame('published', $saved['status']);
                $this->assertSame($index === 1 && $differentContent ? 'Different' : 'Body', $saved['body']);
            }
        }
        $this->assertSame(4, (new AnnouncementModel())->countAllResults());
    }
}
