<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Repositories\AccountRepository;
use App\Services\Authentication;
use App\Services\Security;
use PDO;

// ล็อกอิน สมัครสมาชิก และออกจากระบบ (ฟอร์ม POST แล้ว redirect กลับหน้าเดิมพร้อมข้อความแจ้ง)
/** Login, registration and logout: form posts that redirect back to the page with a flash message. */
final class AuthController extends Controller
{
    private readonly AccountRepository $accounts;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->accounts = new AccountRepository($pdo);
    }

    // ล็อกอิน: ตรวจอีเมลกับรหัสผ่าน แล้วกลับไปหน้าที่ผู้ใช้อยู่ (login.php)
    public function login(Request $request): Response
    {
        if (!$request->isPost()) {
            return Response::redirect('index.php');
        }
        $error = $this->authenticate($request->session(), $request->string('email'), (string) $request->post('password', ''));
        if ($error !== null) {
            $request->session()->set('login_error', $error);
        }

        return Response::redirect(Security::safeRedirect($request->string('next')));
    }

    // สมัครสมาชิก: ตรวจข้อมูล สร้างบัญชี แล้วกลับไปหน้าเดิม (register.php)
    public function register(Request $request): Response
    {
        if (!$request->isPost()) {
            return Response::redirect('index.php');
        }
        $session = $request->session();
        $error = $this->createAccount(
            $request->string('username'),
            $request->string('email'),
            (string) $request->post('password', ''),
            (string) $request->post('confirm_password', '')
        );
        $session->set($error === null ? 'reg_success' : 'reg_error', $error ?? 'สมัครบัญชีสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ');

        return Response::redirect(Security::safeRedirect($request->string('next')));
    }

    // ออกจากระบบ: ล้าง session แล้วกลับหน้าแรก (logout.php)
    public function logout(Request $request): Response
    {
        $request->session()->destroy();

        return Response::redirect('index.php');
    }

    // ตรวจอีเมล/รหัสผ่านกับ DB ถ้าถูกต้องให้บันทึกผู้ใช้ลง session
    /** @return string|null an error message, or null once the user is signed in */
    private function authenticate(Session $session, string $email, string $password): ?string
    {
        $error = 'กรุณากรอกอีเมลและรหัสผ่าน';
        if ($email !== '' && $password !== '') {
            $user = $this->accounts->findByEmail($email);
            $error = match (true) {
                $user === null => 'ไม่พบบัญชีนี้',
                !Authentication::verifyPassword($password, (string) $user['password']) => 'รหัสผ่านไม่ถูกต้อง',
                default => null,
            };
            if ($error === null) {
                $session->login((int) $user['id'], (string) $user['username'], (int) $user['is_admin'] === 1);
            }
        }

        return $error;
    }

    // ตรวจข้อมูลสมัคร เช็กอีเมลซ้ำ แล้วบันทึกผู้ใช้ใหม่ (เข้ารหัสรหัสผ่าน) ลง DB
    /** @return string|null an error message, or null once the account exists */
    private function createAccount(string $username, string $email, string $password, string $confirmation): ?string
    {
        $error = Authentication::validateRegistration($username, $email, $password, $confirmation);
        if ($error === null && $this->accounts->findByEmail($email) !== null) {
            $error = 'อีเมลนี้ถูกใช้แล้ว';
        }
        if ($error === null) {
            $this->accounts->create($username, $email, password_hash($password, PASSWORD_DEFAULT));
        }

        return $error;
    }
}
