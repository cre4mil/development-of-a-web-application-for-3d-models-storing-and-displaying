<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Http\Session;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function testBasicAccessors(): void
    {
        $data = [];
        $session = new Session($data);

        self::assertNull($session->get('a'));
        self::assertSame('d', $session->get('a', 'd'));
        $session->set('a', 1);
        self::assertTrue($session->has('a'));
        self::assertSame(1, $data['a'], 'the wrapped array is shared by reference');
        self::assertSame(1, $session->pull('a'));
        self::assertFalse($session->has('a'));
        self::assertSame('none', $session->pull('a', 'none'));

        $session->set('b', 2);
        $session->forget('b');
        self::assertFalse($session->has('b'));
    }

    public function testLoginAndUserState(): void
    {
        $data = [];
        $session = new Session($data);
        self::assertSame(0, $session->userId());
        self::assertFalse($session->isAdmin());
        self::assertSame('', $session->username());

        $session->login(7, 'alice', true);

        self::assertSame(7, $session->userId());
        self::assertSame('alice', $session->username());
        self::assertTrue($session->isAdmin());

        $session->login(8, 'bob', false);
        self::assertFalse($session->isAdmin());
    }

    public function testAdminFlagRequiresLogin(): void
    {
        $data = ['is_admin' => 1];
        self::assertFalse((new Session($data))->isAdmin());
    }

    public function testCsrfTokenIsCreatedOnceAndValidated(): void
    {
        $data = [];
        $session = new Session($data);

        $token = $session->csrf();
        self::assertSame(32, strlen($token));
        self::assertSame($token, $session->csrf());
        self::assertTrue($session->isValidCsrf($token));
        self::assertFalse($session->isValidCsrf('wrong'));
        self::assertFalse($session->isValidCsrf(''));
        self::assertFalse($session->isValidCsrf(null));

        $empty = [];
        self::assertFalse((new Session($empty))->isValidCsrf('anything'));
    }

    public function testDestroyClearsEverything(): void
    {
        $data = ['uid' => 3, 'x' => 'y'];
        $session = new Session($data);

        $session->destroy();

        self::assertSame([], $data);
        self::assertSame(0, $session->userId());
    }
}
