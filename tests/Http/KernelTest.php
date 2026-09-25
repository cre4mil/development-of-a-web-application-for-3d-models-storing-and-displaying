<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Http\Kernel;
use App\Http\Request;
use App\Http\Response;
use RuntimeException;
use Tests\Support\AppTestCase;

final class KernelTest extends AppTestCase
{
    public function testDispatchBuildsTheControllerAndCallsTheMethod(): void
    {
        $response = Kernel::dispatch(KernelProbe::class, 'ok');

        self::assertSame('ok:' . $this->pdo::class, $response->body());
    }

    public function testExceptionsBecomeAGenericServerError(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'log');
        $previous = ini_set('error_log', $log);

        $response = Kernel::dispatch(KernelProbe::class, 'boom');

        ini_set('error_log', (string) $previous);
        self::assertSame(500, $response->status());
        self::assertStringNotContainsString('secret detail', $response->body());
        self::assertStringContainsString('secret detail', (string) file_get_contents($log), 'the real error is logged');
        unlink($log);
    }

    public function testRunSendsTheResponse(): void
    {
        ob_start();
        Kernel::run(KernelProbe::class, 'ok');

        self::assertStringStartsWith('ok:', (string) ob_get_clean());
    }
}

/** Minimal controller used to exercise the kernel. */
final class KernelProbe
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function ok(Request $request): Response
    {
        return Response::text('ok:' . $this->pdo::class);
    }

    public function boom(Request $request): Response
    {
        throw new RuntimeException('secret detail');
    }
}
