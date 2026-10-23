<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Http\Request;
use App\Http\View;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ViewTest extends TestCase
{
    protected function tearDown(): void
    {
        View::useRoot(null);
    }

    public function testRendersTemplateWithData(): void
    {
        $html = View::render('partials/pagination', ['page' => 2, 'pages' => 4, 'query' => ['search' => 'a b']]);

        self::assertStringContainsString('page=1', $html);
        self::assertStringContainsString('search=a+b', $html);
        self::assertStringContainsString('page-item active', $html);
    }

    public function testMissingTemplateFails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Template not found: nope');

        View::render('nope');
    }

    public function testCustomTemplateRoot(): void
    {
        $root = sys_get_temp_dir() . '/views-' . bin2hex(random_bytes(3));
        mkdir($root);
        file_put_contents($root . '/hello.php', 'Hello <?= e($name) ?>');
        View::useRoot($root);

        self::assertSame('Hello &lt;World&gt;', View::render('hello', ['name' => '<World>']));

        unlink($root . '/hello.php');
        rmdir($root);
    }

    public function testPageWrapsContentInTheLayoutAndPopsFlashMessages(): void
    {
        $session = ['login_error' => 'Wrong password', 'reg_success' => 'Welcome', 'notice' => 'Saved', 'other' => 'kept'];
        $request = new Request(['login' => '1'], [], [], ['REQUEST_URI' => '/x/orders.php?login=1'], $session);

        $response = View::page($request, 'pages/error', ['heading' => 'Oops', 'message' => 'Broken', 'title' => 'Custom title'], 404);

        self::assertSame(404, $response->status());
        self::assertStringContainsString('<title>Custom title</title>', $response->body());
        self::assertStringContainsString('Oops', $response->body());
        self::assertStringContainsString('data-modal="loginModal">Wrong password', $response->body());
        self::assertStringContainsString('Welcome', $response->body());
        self::assertStringContainsString('Saved', $response->body());
        self::assertStringContainsString('กรุณาเข้าสู่ระบบเพื่อใช้งานส่วนนี้', $response->body());
        self::assertStringContainsString('value="orders.php"', $response->body());
        self::assertArrayNotHasKey('login_error', $session, 'flash messages are one-shot');
        self::assertSame('kept', $session['other']);
    }

    public function testLayoutForSignedInUsersHasUploadTools(): void
    {
        $session = ['uid' => 4, 'uname' => 'Alice', 'is_admin' => 1];
        $request = new Request([], [], [], [], $session);

        $html = View::page($request, 'pages/error', ['heading' => 'x', 'message' => 'y'])->body();

        self::assertStringContainsString('id="uploadModal"', $html);
        self::assertStringContainsString('id="editModal"', $html);
        self::assertStringContainsString('js/upload.js', $html);
        self::assertStringContainsString('admin.php', $html);
        self::assertStringNotContainsString('id="registerModal"', $html);
    }
}
