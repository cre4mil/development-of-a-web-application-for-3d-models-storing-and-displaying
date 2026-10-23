<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\Config;

// แสดงผล template ใน frontend/templates และครอบหน้าด้วย layout.php
/** Renders PHP templates from frontend/templates and wraps pages in the shared layout. */
final class View
{
    private static ?string $root = null;

    // เปลี่ยนโฟลเดอร์ template (ใช้ในเทสต์)
    public static function useRoot(?string $root): void
    {
        self::$root = $root;
    }

    // โหลดไฟล์ template พร้อมตัวแปร แล้วคืนเป็น HTML
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = []): string
    {
        $file = (self::$root ?? Config::root() . '/frontend/templates') . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new TemplateNotFoundException("Template not found: {$template}");
        }
        $render = static function (string $__file, array $__data): void {
            extract($__data, EXTR_SKIP);
            include $__file; // NOSONAR -- a template is rendered many times per request, include_once would skip repeats
        };
        ob_start();
        $render($file, $data);

        return (string) ob_get_clean();
    }

    // สร้างหน้าเต็ม: ใส่ข้อมูลผู้ใช้ CSRF ข้อความแจ้งเตือน แล้วครอบด้วย layout.php
    /**
     * Renders a page template inside the site layout.
     *
     * @param array<string, mixed> $data
     */
    public static function page(Request $request, string $template, array $data = [], int $status = 200): Response
    {
        $session = $request->session();
        $layout = $data + [
            'title' => '3D Gallery',
            'nav' => '',
            'scripts' => [],
            'flash' => self::flash($session),
            'next' => $request->page(),
            'openLogin' => $request->query('login') !== null,
            'user' => [
                'id' => $session->userId(),
                'name' => $session->username(),
                'admin' => $session->isAdmin(),
            ],
            'csrf' => $session->csrf(),
        ];
        $layout['content'] = self::render($template, $layout);

        return Response::html(self::render('layout', $layout), $status);
    }

    // ดึงข้อความแจ้งเตือนครั้งเดียวจาก session (เช่น ล็อกอินผิด สมัครสำเร็จ)
    /**
     * Pops one-shot messages set by controllers.
     *
     * @return list<array{type: string, message: string, modal: string}>
     */
    private static function flash(Session $session): array
    {
        $messages = [];
        foreach ([
            'login_error' => ['danger', 'loginModal'],
            'reg_error' => ['danger', 'registerModal'],
            'reg_success' => ['success', 'loginModal'],
            'notice' => ['success', ''],
            'error' => ['danger', ''],
        ] as $key => [$type, $modal]) {
            $message = (string) $session->pull($key, '');
            if ($message !== '') {
                $messages[] = ['type' => $type, 'message' => $message, 'modal' => $modal];
            }
        }

        return $messages;
    }
}
