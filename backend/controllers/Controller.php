<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use PDO;
use Throwable;

/**
 * Base class for request handlers.
 *
 * JSON endpoints list their `action => method` pairs in ACTIONS and are dispatched by
 * handle(); actions abort with abort()/guard() instead of returning early, and handle()
 * turns the resulting HttpException into a JSON error. Page controllers expose one public
 * method per page.
 */
abstract class Controller
{
    protected const MODEL_NOT_FOUND = 'ไม่พบโมเดล';
    protected const INVALID_DATA = 'ข้อมูลไม่ถูกต้อง';

    /** @var array<string, string> */
    protected const ACTIONS = [];

    public function __construct(protected readonly PDO $pdo)
    {
    }

    public function handle(Request $request): Response
    {
        $method = static::ACTIONS[$request->string('action')] ?? null;
        try {
            $this->abortUnless($method !== null, 400, 'bad_action', 'Unknown action');

            return $this->{(string) $method}($request);
        } catch (HttpException $e) {
            return $this->fail($e->error, $e->status, $e->getMessage());
        } catch (Throwable $e) {
            error_log(sprintf('%s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));

            return $this->fail('server_error', 500, 'เกิดข้อผิดพลาดของเซิร์ฟเวอร์ กรุณาลองใหม่');
        }
    }

    /** @param array<string, mixed> $data */
    protected function ok(array $data = [], int $status = 200): Response
    {
        return Response::json(['ok' => true] + $data, $status);
    }

    protected function fail(string $error, int $status = 400, ?string $message = null): Response
    {
        return Response::json(['ok' => false, 'error' => $error, 'message' => $message ?? $error], $status);
    }

    /** Aborts the current JSON request with an error response. */
    protected function abort(int $status, string $error, string $message): never
    {
        throw new HttpException($status, $error, $message);
    }

    protected function abortUnless(bool $condition, int $status, string $error, string $message): void
    {
        if (!$condition) {
            $this->abort($status, $error, $message);
        }
    }

    /**
     * Standard checks for JSON endpoints: HTTP method, signed-in user, admin role and CSRF token.
     *
     * @throws HttpException
     */
    protected function guard(Request $request, bool $post = true, bool $admin = false): void
    {
        $session = $request->session();
        $this->abortUnless(!$post || $request->isPost(), 405, 'method_not_allowed', 'method_not_allowed');
        $this->abortUnless($session->userId() > 0, 401, 'not_login', 'กรุณาเข้าสู่ระบบก่อน');
        $this->abortUnless(!$admin || $session->isAdmin(), 403, 'forbidden', 'ต้องเป็นผู้ดูแลระบบเท่านั้น');
        $this->abortUnless(!$post || $request->hasValidCsrf(), 403, 'bad_csrf', 'เซสชันหมดอายุ กรุณารีเฟรชหน้าเว็บ');
    }

    /** Redirects visitors who are not logged in (or not admins) away from a page. */
    protected function pageGuard(Request $request, bool $admin = false): ?Response
    {
        $session = $request->session();
        if ($session->userId() === 0) {
            return Response::redirect('index.php?login=1');
        }

        return $admin && !$session->isAdmin() ? Response::redirect('index.php') : null;
    }

    protected function notFound(Request $request, string $message = 'ไม่พบหน้าที่คุณต้องการ'): Response
    {
        return View::page($request, 'pages/error', ['title' => 'ไม่พบหน้า', 'heading' => '404', 'message' => $message], 404);
    }
}
