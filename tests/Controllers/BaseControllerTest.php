<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\SocialController;
use Tests\Support\AppTestCase;

/** Behaviour shared by every JSON controller (dispatch, error format). */
final class BaseControllerTest extends AppTestCase
{
    public function testUnknownActionsGetAJsonError(): void
    {
        $response = (new SocialController($this->pdo))->handle($this->request(['action' => 'explode']));

        self::assertSame(400, $response->status());
        self::assertSame(['ok' => false, 'error' => 'bad_action', 'message' => 'Unknown action'], $this->json($response));
    }

    public function testMissingActionIsAlsoRejected(): void
    {
        self::assertSame('bad_action', $this->json((new SocialController($this->pdo))->handle($this->request()))['error']);
    }

    public function testUnexpectedFailuresBecomeAJsonServerError(): void
    {
        $this->pdo->exec('DROP TABLE likes');
        $owner = $this->addUser('owner');
        $model = $this->addModel($owner);
        $this->loginAs($owner, 'owner');
        $log = tempnam(sys_get_temp_dir(), 'log');
        $previous = ini_set('error_log', $log);

        $response = (new SocialController($this->pdo))->handle($this->post(['action' => 'like', 'id' => (string) $model]));

        ini_set('error_log', (string) $previous);
        self::assertSame(500, $response->status());
        self::assertSame('server_error', $this->json($response)['error']);
        self::assertStringContainsString('likes', (string) file_get_contents($log));
        unlink($log);
    }
}
