<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\SocialController;
use Tests\Support\AppTestCase;

final class SocialControllerTest extends AppTestCase
{
    private SocialController $controller;
    private int $alice;
    private int $bob;
    private int $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new SocialController($this->pdo);
        $this->alice = $this->addUser('alice');
        $this->bob = $this->addUser('bob');
        $this->model = $this->addModel($this->bob);
    }

    public function testWriteActionsNeedPostLoginAndCsrf(): void
    {
        foreach (['like', 'save', 'follow', 'comment_add', 'comment_delete'] as $action) {
            self::assertSame(405, $this->controller->handle($this->request(['action' => $action]))->status(), $action);
            self::assertSame(401, $this->controller->handle($this->post(['action' => $action]))->status(), $action);
        }
        $this->loginAs($this->alice, 'alice');
        $noToken = new \App\Http\Request([], ['action' => 'like', 'id' => (string) $this->model], [], ['REQUEST_METHOD' => 'POST'], $this->session);
        self::assertSame(403, $this->controller->handle($noToken)->status());
    }

    public function testLikeTogglesAndReturnsTheCount(): void
    {
        $this->loginAs($this->alice, 'alice');

        $on = $this->json($this->controller->handle($this->post(['action' => 'like', 'id' => (string) $this->model])));
        self::assertTrue($on['liked']);
        self::assertSame(1, $on['count']);

        $this->loginAs($this->bob, 'bob');
        self::assertSame(2, $this->json($this->controller->handle($this->post(['action' => 'like', 'id' => (string) $this->model])))['count']);

        $this->loginAs($this->alice, 'alice');
        $off = $this->json($this->controller->handle($this->post(['action' => 'like', 'id' => (string) $this->model])));
        self::assertFalse($off['liked']);
        self::assertSame(1, $off['count']);
    }

    public function testLikeAndSaveRejectUnknownAndHiddenModels(): void
    {
        $hidden = $this->addModel($this->bob, ['is_public' => 0]);
        $this->loginAs($this->alice, 'alice');

        foreach (['like', 'save'] as $action) {
            self::assertSame(404, $this->controller->handle($this->post(['action' => $action, 'id' => '999']))->status(), $action);
            self::assertSame(404, $this->controller->handle($this->post(['action' => $action, 'id' => (string) $hidden]))->status(), $action);
        }

        $this->loginAs($this->bob, 'bob');
        self::assertTrue($this->json($this->controller->handle($this->post(['action' => 'like', 'id' => (string) $hidden])))['liked'], 'owners can like their own hidden models');
    }

    public function testSaveToggles(): void
    {
        $this->loginAs($this->alice, 'alice');

        self::assertTrue($this->json($this->controller->handle($this->post(['action' => 'save', 'id' => (string) $this->model])))['saved']);
        self::assertFalse($this->json($this->controller->handle($this->post(['action' => 'save', 'id' => (string) $this->model])))['saved']);
    }

    public function testFollowToggleRules(): void
    {
        $this->loginAs($this->alice, 'alice');

        $on = $this->json($this->controller->handle($this->post(['action' => 'follow', 'user_id' => (string) $this->bob])));
        self::assertTrue($on['following']);
        self::assertSame(1, $on['followers']);

        $off = $this->json($this->controller->handle($this->post(['action' => 'follow', 'user_id' => (string) $this->bob])));
        self::assertFalse($off['following']);
        self::assertSame(0, $off['followers']);

        self::assertSame(422, $this->controller->handle($this->post(['action' => 'follow', 'user_id' => (string) $this->alice]))->status());
        self::assertSame(404, $this->controller->handle($this->post(['action' => 'follow', 'user_id' => '999']))->status());
    }

    public function testCommentsCanBeListedByAnyoneWhoCanSeeTheModel(): void
    {
        $this->pdo->exec("INSERT INTO comments (model_id, user_id, body) VALUES ({$this->model}, {$this->alice}, 'hello')");
        $hidden = $this->addModel($this->bob, ['is_public' => 0]);

        $guest = $this->json($this->controller->handle($this->request(['action' => 'comments', 'model_id' => (string) $this->model])));
        self::assertSame('hello', $guest['comments'][0]['body']);
        self::assertSame(0, $guest['uid']);
        self::assertFalse($guest['admin']);

        self::assertSame(404, $this->controller->handle($this->request(['action' => 'comments', 'model_id' => (string) $hidden]))->status());
        self::assertSame(404, $this->controller->handle($this->request(['action' => 'comments', 'model_id' => '0']))->status());

        $admin = $this->addUser('admin', true);
        $this->loginAs($admin, 'admin', true);
        $moderator = $this->json($this->controller->handle($this->request(['action' => 'comments', 'model_id' => (string) $hidden])));
        self::assertTrue($moderator['admin']);
    }

    public function testAddCommentValidatesAndStores(): void
    {
        $this->loginAs($this->alice, 'alice');
        $add = fn (array $extra) => $this->controller->handle($this->post(['action' => 'comment_add', 'model_id' => (string) $this->model] + $extra));

        self::assertSame('empty_comment', $this->json($add(['body' => '   ']))['error']);
        self::assertSame('too_long', $this->json($add(['body' => str_repeat('ก', 1001)]))['error']);
        self::assertSame(404, $this->controller->handle($this->post(['action' => 'comment_add', 'model_id' => '999', 'body' => 'x']))->status());

        $ok = $add(['body' => '  Nice model  ']);
        self::assertSame(201, $ok->status());
        self::assertSame('Nice model', $this->json($ok)['comment']['body']);
        self::assertSame('alice', $this->json($ok)['comment']['username']);
        self::assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM comments'));
    }

    public function testDeleteCommentOnlyByAuthorOrAdmin(): void
    {
        $this->pdo->exec("INSERT INTO comments (id, model_id, user_id, body) VALUES (1, {$this->model}, {$this->alice}, 'a'), (2, {$this->model}, {$this->alice}, 'b')");
        $admin = $this->addUser('admin', true);

        $this->loginAs($this->bob, 'bob');
        self::assertSame(404, $this->controller->handle($this->post(['action' => 'comment_delete', 'id' => '1']))->status());

        $this->loginAs($this->alice, 'alice');
        self::assertTrue($this->json($this->controller->handle($this->post(['action' => 'comment_delete', 'id' => '1'])))['ok']);

        $this->loginAs($admin, 'admin', true);
        self::assertTrue($this->json($this->controller->handle($this->post(['action' => 'comment_delete', 'id' => '2'])))['ok']);
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM comments'));
    }
}
