<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

class LogoutTokenMismatchTest extends TestCase
{
    public function test_expired_admin_logout_token_redirects_to_admin_login_and_invalidates_session(): void
    {
        $this->assertLogoutTokenMismatchRedirects('/admin/logout', '/admin/login');
    }

    public function test_expired_portal_logout_token_redirects_to_account_selection_and_invalidates_session(): void
    {
        $this->assertLogoutTokenMismatchRedirects('/logout', '/');
    }

    public function test_stale_csrf_token_on_non_logout_post_still_returns_page_expired(): void
    {
        $request = Request::create('/admin/login', 'POST');

        $response = app(ExceptionHandler::class)->render($request, new TokenMismatchException);

        $this->assertSame(419, $response->getStatusCode());
    }

    private function assertLogoutTokenMismatchRedirects(string $path, string $expectedRedirect): void
    {
        $session = app('session')->driver();
        $session->start();
        $session->put('supabase_user', ['id' => 'user-123']);
        $session->put('admin_id', 123);

        $request = Request::create($path, 'POST');
        $request->setLaravelSession($session);

        $response = app(ExceptionHandler::class)->render($request, new TokenMismatchException);

        $this->assertSame(302, $response->getStatusCode());
        $redirectPath = parse_url((string) $response->headers->get('Location'), PHP_URL_PATH) ?: '/';
        $this->assertSame($expectedRedirect, $redirectPath);
        $this->assertFalse($session->has('supabase_user'));
        $this->assertFalse($session->has('admin_id'));
    }
}
