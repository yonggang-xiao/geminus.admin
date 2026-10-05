<?php

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class AuthViewsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    public function testLoginPostWithoutCsrfIsRejected(): void
    {
        $this->expectException(SecurityException::class);

        $this->post('/en/login');
    }

    public function testLoginFormSubmitsWithCsrf(): void
    {
        $loginPage = $this->get('/en/login')->response()->getBody();
        $this->assertStringContainsString('name="' . csrf_token() . '"', $loginPage);
        $this->assertStringContainsString('src="/static/js/form-submission.js"', $loginPage);
        $this->assertStringContainsString('@tabler/core@1.6.1/dist/css/tabler.min.css', $loginPage);
        $this->assertStringContainsString('href="/static/css/theme.css"', $loginPage);
        $this->assertStringContainsString('class="row align-items-center g-4"', $loginPage);
        $this->assertStringContainsString('class="col-lg d-none d-lg-block"', $loginPage);
        $this->assertStringContainsString('src="/static/images/undraw_login_weas.svg"', $loginPage);
        $this->assertStringContainsString('for="login-email"', $loginPage);
        $this->assertStringNotContainsString('bootstrap@5.2.3', $loginPage);

        $result = $this->post('/en/login', [
            csrf_token() => csrf_hash(),
            'email'      => 'nobody@example.com',
            'password'   => 'incorrect',
        ]);

        $result->assertRedirect();
    }

    public function testMagicLinkFormUsesPublicLayout(): void
    {
        $page = $this->get('/en/login/magic-link');

        $page->assertOK();
        $this->assertStringContainsString('@tabler/core@1.6.1/dist/css/tabler.min.css', $page->response()->getBody());
        $this->assertStringContainsString('src="/static/js/form-submission.js"', $page->response()->getBody());
        $this->assertStringContainsString('for="magic-link-email"', $page->response()->getBody());
        $this->assertStringContainsString('name="' . csrf_token() . '"', $page->response()->getBody());
    }
}
