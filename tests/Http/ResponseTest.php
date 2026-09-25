<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testHtmlTextAndRedirect(): void
    {
        $html = Response::html('<p>hi</p>', 201);
        self::assertSame(201, $html->status());
        self::assertSame('<p>hi</p>', $html->body());
        self::assertSame('text/html; charset=utf-8', $html->header('Content-Type'));
        self::assertNull($html->header('X-Missing'));

        $text = Response::text('plain');
        self::assertSame('text/plain; charset=utf-8', $text->header('Content-Type'));

        $redirect = Response::redirect('index.php');
        self::assertSame(302, $redirect->status());
        self::assertSame('index.php', $redirect->header('Location'));
        self::assertSame('', $redirect->body());
    }

    public function testJsonKeepsUnicodeAndSlashesReadable(): void
    {
        $response = Response::json(['ok' => true, 'message' => 'สวัสดี', 'url' => 'a/b'], 202);

        self::assertSame(202, $response->status());
        self::assertSame('{"ok":true,"message":"สวัสดี","url":"a/b"}', $response->body());
        self::assertSame(['ok' => true, 'message' => 'สวัสดี', 'url' => 'a/b'], $response->data());
        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertNull(Response::text('not json')->data());
    }

    public function testDownloadDescribesTheAttachment(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dl');
        file_put_contents($path, 'payload');

        $response = Response::download($path, "bad\"name\r\n.glb");

        self::assertSame('attachment; filename="badname.glb"', $response->header('Content-Disposition'));
        self::assertSame('7', $response->header('Content-Length'));

        ob_start();
        $response->send();
        self::assertSame('payload', ob_get_clean());
        unlink($path);
    }

    public function testSendWritesTheBody(): void
    {
        ob_start();
        Response::html('<b>body</b>')->send();

        self::assertSame('<b>body</b>', ob_get_clean());
    }
}
