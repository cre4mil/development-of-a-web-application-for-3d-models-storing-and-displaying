<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\AuthController;
use Tests\Support\AppTestCase;

final class AuthControllerTest extends AppTestCase
{
    private AuthController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new AuthController($this->pdo);
    }

    public function testLoginRequiresPost(): void
    {
        $response = $this->controller->login($this->request());

        self::assertSame(302, $response->status());
        self::assertSame('index.php', $response->header('Location'));
    }

    public function testLoginRejectsMissingUnknownAndWrongCredentials(): void
    {
        $this->addUser('alice', false, 'secret');

        $this->controller->login($this->request([], ['email' => '', 'password' => '']));
        self::assertSame('กรุณากรอกอีเมลและรหัสผ่าน', $this->session['login_error']);

        $this->controller->login($this->request([], ['email' => 'nobody@gmail.com', 'password' => 'x']));
        self::assertSame('ไม่พบบัญชีนี้', $this->session['login_error']);

        $this->controller->login($this->request([], ['email' => 'alice@gmail.com', 'password' => 'wrong']));
        self::assertSame('รหัสผ่านไม่ถูกต้อง', $this->session['login_error']);
        self::assertArrayNotHasKey('uid', $this->session);
    }

    public function testSuccessfulLoginStartsASessionAndReturnsToThePreviousPage(): void
    {
        $id = $this->addUser('Alice', true, 'secret');

        $response = $this->controller->login($this->request([], ['email' => 'alice@gmail.com', 'password' => 'secret', 'next' => 'model.php?id=4']));

        self::assertSame('model.php?id=4', $response->header('Location'));
        self::assertSame($id, $this->session['uid']);
        self::assertSame('Alice', $this->session['uname']);
        self::assertSame(1, $this->session['is_admin']);
    }

    public function testLoginNeverRedirectsToExternalSites(): void
    {
        $this->addUser('alice', false, 'secret');

        $response = $this->controller->login($this->request([], ['email' => 'alice@gmail.com', 'password' => 'secret', 'next' => 'https://evil.example']));

        self::assertSame('index.php', $response->header('Location'));
    }

    public function testLegacyPlaintextPasswordsStillWork(): void
    {
        $this->exec("INSERT INTO users (username, email, password) VALUES ('old', 'old@gmail.com', 'plain')");

        $this->controller->login($this->request([], ['email' => 'old@gmail.com', 'password' => 'plain']));

        self::assertArrayHasKey('uid', $this->session);
    }

    public function testRegistrationValidation(): void
    {
        $this->addUser('taken');
        $post = ['username' => 'bob', 'email' => 'bob@gmail.com', 'password' => 'pw', 'confirm_password' => 'pw'];

        $this->controller->register($this->request([], ['confirm_password' => 'different'] + $post));
        self::assertSame('ยืนยันรหัสผ่านไม่ตรงกัน', $this->session['reg_error']);

        $this->controller->register($this->request([], ['email' => 'bob@example.com'] + $post));
        self::assertStringContainsString('gmail.com', $this->session['reg_error']);

        $this->controller->register($this->request([], ['email' => 'taken@gmail.com'] + $post));
        self::assertSame('อีเมลนี้ถูกใช้แล้ว', $this->session['reg_error']);
        self::assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM users'));
    }

    public function testRegistrationCreatesAHashedAccount(): void
    {
        $response = $this->controller->register($this->request([], ['username' => 'Bob', 'email' => 'bob@gmail.com', 'password' => 'pw123', 'confirm_password' => 'pw123', 'next' => 'like.php']));

        self::assertSame('like.php', $response->header('Location'));
        self::assertNotEmpty($this->session['reg_success']);
        $hash = (string) $this->scalar("SELECT password FROM users WHERE email = 'bob@gmail.com'");
        self::assertTrue(password_verify('pw123', $hash));
        self::assertSame(0, (int) $this->scalar("SELECT is_admin FROM users WHERE email = 'bob@gmail.com'"));
    }

    public function testRegisterRequiresPost(): void
    {
        self::assertSame('index.php', $this->controller->register($this->request())->header('Location'));
    }

    public function testLogoutClearsTheSession(): void
    {
        $this->loginAs(3, 'alice');

        $response = $this->controller->logout($this->request());

        self::assertSame('index.php', $response->header('Location'));
        self::assertSame([], $this->session);
    }
}
