<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testReadsQueryPostAndInputWithPostPriority(): void
    {
        $request = new Request(['a' => 'query', 'q' => ' spaced '], ['a' => 'post', 'n' => '42', 'list' => [1, 2]], [], ['REQUEST_METHOD' => 'post']);

        self::assertSame('query', $request->query('a'));
        self::assertSame('post', $request->post('a'));
        self::assertSame('post', $request->input('a'));
        self::assertSame('spaced', $request->string('q'));
        self::assertSame('fallback', $request->string('missing', 'fallback'));
        self::assertSame('fallback', $request->string('list', 'fallback'));
        self::assertSame(42, $request->int('n'));
        self::assertSame(7, $request->int('missing', 7));
        self::assertSame([1, 2], $request->list('list'));
        self::assertSame([], $request->list('n'));
        self::assertSame('x', $request->query('nope', 'x'));
        self::assertSame('y', $request->post('nope', 'y'));
        self::assertTrue($request->isPost());
        self::assertSame('POST', $request->method());
    }

    public function testDefaultsToGetAndRootUri(): void
    {
        $request = new Request();

        self::assertSame('GET', $request->method());
        self::assertFalse($request->isPost());
        self::assertSame('/', $request->uri());
        self::assertSame('index.php', $request->page());
    }

    public function testPageDropsDirectoriesAndTheLoginFlag(): void
    {
        $request = new Request(['id' => '3', 'login' => '1'], [], [], ['REQUEST_URI' => '/site/model.php?id=3&login=1']);
        self::assertSame('model.php?id=3', $request->page());

        $plain = new Request([], [], [], ['REQUEST_URI' => '/site/profile.php']);
        self::assertSame('profile.php', $plain->page());
    }

    public function testFilesAndHeaders(): void
    {
        $request = new Request([], [], ['model' => ['name' => 'a.glb'], 'broken' => 'text'], ['HTTP_X_CSRF_TOKEN' => 'abc']);

        self::assertSame(['name' => 'a.glb'], $request->file('model'));
        self::assertNull($request->file('broken'));
        self::assertNull($request->file('missing'));
        self::assertSame('abc', $request->header('X-CSRF-Token'));
        self::assertNull($request->header('X-Other'));
    }

    public function testDetectsBodiesDiscardedByPostMaxSize(): void
    {
        self::assertTrue((new Request([], [], [], ['CONTENT_LENGTH' => '99999999']))->bodyWasDiscarded());
        self::assertFalse((new Request([], ['a' => 'b'], [], ['CONTENT_LENGTH' => '99999999']))->bodyWasDiscarded());
        self::assertFalse((new Request())->bodyWasDiscarded());
    }

    public function testCsrfTokenFromHeaderOrForm(): void
    {
        $session = ['csrf' => 'secret'];
        $viaHeader = new Request([], [], [], ['HTTP_X_CSRF_TOKEN' => 'secret'], $session);
        $viaForm = new Request([], ['csrf' => 'secret'], [], [], $session);
        $wrong = new Request([], ['csrf' => 'nope'], [], [], $session);
        $none = new Request([], [], [], [], $session);
        $nonString = new Request([], ['csrf' => ['x']], [], [], $session);

        self::assertTrue($viaHeader->hasValidCsrf());
        self::assertTrue($viaForm->hasValidCsrf());
        self::assertFalse($wrong->hasValidCsrf());
        self::assertFalse($none->hasValidCsrf());
        self::assertNull($nonString->csrfToken());
    }

    public function testFromGlobalsSharesTheSessionArray(): void
    {
        $previous = [$_GET, $_POST, $_FILES, $_SERVER, $_SESSION ?? null];
        $_GET = ['id' => '5'];
        $_POST = [];
        $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SESSION);

        $request = Request::fromGlobals();
        $request->session()->set('flag', 'on');

        self::assertSame(5, $request->int('id'));
        self::assertSame('on', $_SESSION['flag']);
        [$_GET, $_POST, $_FILES, $_SERVER] = $previous;
        $_SESSION = $previous[4] ?? [];
    }
}
