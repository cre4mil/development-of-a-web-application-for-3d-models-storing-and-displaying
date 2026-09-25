<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\ModelController;
use App\Http\Request;
use App\Services\ModelConverter;
use App\Services\ModelFiles;
use Tests\Support\AppTestCase;

/** Upload / edit / delete / visibility API. */
final class ModelApiTest extends AppTestCase
{
    private ModelController $controller;
    private int $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->addUser('Owner');
        $this->controller = $this->controller($this->files());
    }

    private function controller(ModelFiles $files): ModelController
    {
        return new ModelController($this->pdo, $files, new ModelConverter($files, '', ''));
    }

    /** A files service whose mover fails for names matching $failOn. */
    private function failingFiles(string $failOn): ModelFiles
    {
        return new ModelFiles($this->uploads, static fn (string $from, string $to): bool => !str_contains($to, $failOn) && copy($from, $to));
    }

    // ── Guards ───────────────────────────────────────────────────────

    public function testEveryWriteActionRequiresPostLoginAndCsrf(): void
    {
        foreach (['create', 'update', 'delete', 'visibility'] as $action) {
            $get = $this->controller->handle($this->request(['action' => $action]));
            self::assertSame(405, $get->status(), "{$action} GET");

            $guest = $this->controller->handle($this->post(['action' => $action]));
            self::assertSame(401, $guest->status(), "{$action} guest");
        }

        $this->loginAs($this->owner, 'Owner');
        $noToken = new Request([], ['action' => 'delete', 'id' => '1'], [], ['REQUEST_METHOD' => 'POST'], $this->session);
        self::assertSame(403, $this->controller->handle($noToken)->status());
        self::assertSame('bad_csrf', $this->json($this->controller->handle($noToken))['error']);
    }

    public function testGetRequiresLoginButNotCsrf(): void
    {
        self::assertSame(401, $this->controller->handle($this->request(['action' => 'get', 'id' => '1']))->status());
    }

    // ── get ──────────────────────────────────────────────────────────

    public function testGetReturnsTheEditableFieldsForTheOwnerOnly(): void
    {
        $id = $this->addModel($this->owner, ['title' => 'Chair', 'description' => 'D', 'license' => 'CC0', 'price' => 12]);
        $this->addTags($id, 'b', 'a');
        $stranger = $this->addUser('Stranger');

        $this->loginAs($this->owner, 'Owner');
        $data = $this->json($this->controller->handle($this->request(['action' => 'get', 'id' => (string) $id])));
        self::assertTrue($data['ok']);
        self::assertSame('Chair', $data['model']['title']);
        self::assertSame('a, b', $data['model']['tags']);
        self::assertEquals(12, $data['model']['price']);
        self::assertSame('CC0', $data['model']['license']);

        $this->loginAs($stranger, 'Stranger');
        self::assertSame(404, $this->controller->handle($this->request(['action' => 'get', 'id' => (string) $id]))->status());
        self::assertSame(404, $this->controller->handle($this->request(['action' => 'get', 'id' => '999']))->status());
    }

    // ── create ───────────────────────────────────────────────────────

    public function testCreateStoresFilesRowsTagsAndReturnsTheModelUrl(): void
    {
        $this->loginAs($this->owner, 'Owner');

        $response = $this->controller->handle($this->post([
            'action' => 'create', 'title' => ' New chair ', 'description' => 'Comfy', 'price' => '19.999',
            'license' => 'CC BY-SA', 'tags' => 'Furniture, Wood ,furniture',
        ], ['model' => $this->upload('chair.GLB', 'glb-bytes'), 'thumb' => $this->upload('cover.png', 'png-bytes')]));

        $data = $this->json($response);
        self::assertSame(201, $response->status());
        self::assertTrue($data['ok']);
        $id = (int) $data['id'];
        self::assertSame('model.php?id=' . $id, $data['url']);

        $row = $this->pdo->query("SELECT * FROM models WHERE id = {$id}")->fetch();
        self::assertSame('New chair', $row['title']);
        self::assertSame(20.0, (float) $row['price']);
        self::assertSame('CC BY-SA', $row['license']);
        self::assertSame($this->owner, (int) $row['user_id']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}\.glb$/', $row['filename']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}_thumb\.png$/', $row['thumb']);
        self::assertSame($row['filename'], $row['file_glb'], 'glb uploads register themselves as the GLB download');
        self::assertFileExists($this->uploads . $row['filename']);
        self::assertFileExists($this->uploads . $row['thumb']);
        self::assertSame(['furniture', 'wood'], $this->pdo->query('SELECT t.name FROM tags t JOIN model_tags mt ON mt.tag_id = t.id ORDER BY t.name')->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function testCreateWithoutThumbnailUsesTheEmptyPlaceholder(): void
    {
        $this->loginAs($this->owner, 'Owner');

        $response = $this->controller->handle($this->post(['action' => 'create', 'title' => 'Plain', 'license' => 'weird'], ['model' => $this->upload('a.obj')]));

        self::assertSame(201, $response->status());
        $row = $this->pdo->query('SELECT * FROM models')->fetch();
        self::assertSame('', $row['thumb']);
        self::assertSame('CC BY', $row['license'], 'unknown licenses fall back to the default');
        self::assertSame(0.0, (float) $row['price']);
        self::assertSame($row['filename'], $row['file_obj']);
    }

    public function testCreateRejectsInvalidInput(): void
    {
        $this->loginAs($this->owner, 'Owner');
        $cases = [
            'no title' => [['action' => 'create', 'title' => '  '], ['model' => $this->upload('a.glb')], 'title_required'],
            'no model' => [['action' => 'create', 'title' => 'T'], [], 'invalid_upload'],
            'bad model type' => [['action' => 'create', 'title' => 'T'], ['model' => $this->upload('shell.php')], 'invalid_upload'],
            'bad thumb type' => [['action' => 'create', 'title' => 'T'], ['model' => $this->upload('a.glb'), 'thumb' => $this->upload('x.svg')], 'invalid_upload'],
        ];

        foreach ($cases as $label => [$post, $files, $error]) {
            $response = $this->controller->handle($this->post($post, $files));
            self::assertSame(422, $response->status(), $label);
            self::assertSame($error, $this->json($response)['error'], $label);
        }
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM models'));
    }

    public function testCreateExplainsWhenTheServerDroppedAnOversizedBody(): void
    {
        $this->loginAs($this->owner, 'Owner');

        $response = $this->controller->handle(new Request(['action' => 'create'], [], [], ['REQUEST_METHOD' => 'POST', 'CONTENT_LENGTH' => '999999999'], $this->session));

        self::assertSame(413, $response->status());
        self::assertSame('too_large', $this->json($response)['error']);
    }

    public function testCreateReportsStorageFailuresAndCleansUp(): void
    {
        $this->loginAs($this->owner, 'Owner');

        $modelFails = $this->controller($this->failingFiles('.glb'));
        $response = $modelFails->handle($this->post(['action' => 'create', 'title' => 'T'], ['model' => $this->upload('a.glb')]));
        self::assertSame(500, $response->status());
        self::assertSame('save_failed', $this->json($response)['error']);

        $thumbFails = $this->controller($this->failingFiles('_thumb'));
        $response = $thumbFails->handle($this->post(['action' => 'create', 'title' => 'T'], ['model' => $this->upload('a.glb'), 'thumb' => $this->upload('a.png')]));
        self::assertSame(500, $response->status());
        self::assertSame([], glob($this->uploads . '*'), 'the model file is removed when the thumbnail cannot be saved');
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM models'));
    }

    // ── update ───────────────────────────────────────────────────────

    public function testUpdateChangesMetadataAndTags(): void
    {
        $id = $this->addModel($this->owner, ['title' => 'Old', 'price' => 5]);
        $this->addTags($id, 'old');
        $this->loginAs($this->owner, 'Owner');

        $response = $this->controller->handle($this->post([
            'action' => 'update', 'id' => (string) $id, 'title' => 'New', 'description' => 'Desc', 'license' => 'CC0', 'price' => '', 'tags' => 'x, y',
        ]));

        self::assertSame(['ok' => true, 'title' => 'New'], $this->json($response));
        $row = $this->pdo->query("SELECT * FROM models WHERE id = {$id}")->fetch();
        self::assertSame('CC0', $row['license']);
        self::assertSame(0.0, (float) $row['price']);
        self::assertSame(['x', 'y'], $this->pdo->query('SELECT t.name FROM tags t JOIN model_tags mt ON mt.tag_id = t.id ORDER BY t.name')->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function testUpdateReplacesFilesAndDeletesTheOldOnes(): void
    {
        $id = $this->addModel($this->owner, ['filename' => 'old.glb', 'thumb' => 'old.png', 'file_glb' => 'old.glb', 'file_obj' => 'old_converted.obj']);
        foreach (['old.png', 'old_converted.obj'] as $name) {
            file_put_contents($this->uploads . $name, 'x');
        }
        $this->loginAs($this->owner, 'Owner');

        $response = $this->controller->handle($this->post(
            ['action' => 'update', 'id' => (string) $id, 'title' => 'Replaced'],
            ['model' => $this->upload('fresh.obj', 'obj'), 'thumb' => $this->upload('fresh.jpg', 'jpg')]
        ));

        self::assertTrue($this->json($response)['ok']);
        $row = $this->pdo->query("SELECT * FROM models WHERE id = {$id}")->fetch();
        self::assertNotSame('old.glb', $row['filename']);
        self::assertStringEndsWith('.obj', $row['filename']);
        self::assertStringEndsWith('_thumb.jpg', $row['thumb']);
        self::assertSame($row['filename'], $row['file_obj']);
        self::assertNull($row['file_glb']);
        self::assertEqualsCanonicalizing([$row['filename'], $row['thumb']], array_map('basename', glob($this->uploads . '*')));
    }

    public function testUpdateKeepsExistingFilesWhenNoneAreUploaded(): void
    {
        $id = $this->addModel($this->owner, ['filename' => 'keep.glb', 'thumb' => 'keep.png']);
        file_put_contents($this->uploads . 'keep.png', 'x');
        $this->loginAs($this->owner, 'Owner');

        $this->controller->handle($this->post(['action' => 'update', 'id' => (string) $id, 'title' => 'Same']));

        self::assertFileExists($this->uploads . 'keep.glb');
        self::assertFileExists($this->uploads . 'keep.png');
    }

    public function testUpdateRejectsStrangersAndBadInput(): void
    {
        $id = $this->addModel($this->owner);
        $stranger = $this->addUser('Stranger');

        $this->loginAs($stranger, 'Stranger');
        self::assertSame(404, $this->controller->handle($this->post(['action' => 'update', 'id' => (string) $id, 'title' => 'Hack']))->status());

        $this->loginAs($this->owner, 'Owner');
        self::assertSame(422, $this->controller->handle($this->post(['action' => 'update', 'id' => (string) $id, 'title' => '']))->status());
        self::assertSame(422, $this->controller->handle($this->post(['action' => 'update', 'id' => (string) $id, 'title' => 'T'], ['model' => $this->upload('x.php')]))->status());
        self::assertSame(422, $this->controller->handle($this->post(['action' => 'update', 'id' => (string) $id, 'title' => 'T'], ['thumb' => $this->upload('x.svg')]))->status());
    }

    public function testUpdateReportsStorageFailures(): void
    {
        $id = $this->addModel($this->owner, ['filename' => 'orig.glb']);
        $this->loginAs($this->owner, 'Owner');

        $modelFails = $this->controller($this->failingFiles('.glb'));
        $response = $modelFails->handle($this->post(['action' => 'update', 'id' => (string) $id, 'title' => 'T'], ['model' => $this->upload('n.glb')]));
        self::assertSame('save_failed', $this->json($response)['error']);
        self::assertFileExists($this->uploads . 'orig.glb', 'the original survives a failed replacement');

        $thumbFails = $this->controller($this->failingFiles('_thumb'));
        $response = $thumbFails->handle($this->post(['action' => 'update', 'id' => (string) $id, 'title' => 'T'], ['thumb' => $this->upload('n.png')]));
        self::assertSame(500, $response->status());
    }

    // ── delete ───────────────────────────────────────────────────────

    public function testDeleteRemovesTheModelForItsOwner(): void
    {
        $id = $this->addModel($this->owner, ['filename' => 'gone.glb']);
        $this->loginAs($this->owner, 'Owner');

        $response = $this->controller->handle($this->post(['action' => 'delete', 'id' => (string) $id]));

        self::assertTrue($this->json($response)['ok']);
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM models'));
        self::assertFileDoesNotExist($this->uploads . 'gone.glb');
    }

    public function testDeleteRefusesStrangersAndModelsThatHaveBeenSold(): void
    {
        $id = $this->addModel($this->owner, ['price' => 9]);
        $stranger = $this->addUser('Stranger');
        $this->loginAs($stranger, 'Stranger');
        self::assertSame(404, $this->controller->handle($this->post(['action' => 'delete', 'id' => (string) $id]))->status());

        $this->exec("INSERT INTO orders (order_ref, buyer_id) VALUES ('R1', ?)", $stranger);
        $this->exec('INSERT INTO order_items (order_id, model_id, creator_id) VALUES (1, ?, ?)', $id, $this->owner);
        $this->loginAs($this->owner, 'Owner');
        $response = $this->controller->handle($this->post(['action' => 'delete', 'id' => (string) $id]));

        self::assertSame(409, $response->status());
        self::assertSame('has_sales', $this->json($response)['error']);
        self::assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM models'));
    }

    // ── visibility ───────────────────────────────────────────────────

    public function testVisibilityCanBeToggledByTheOwner(): void
    {
        $id = $this->addModel($this->owner);
        $this->loginAs($this->owner, 'Owner');

        $hide = $this->controller->handle($this->post(['action' => 'visibility', 'id' => (string) $id, 'is_public' => '0']));
        self::assertSame(0, $this->json($hide)['is_public']);
        self::assertSame(0, (int) $this->scalar('SELECT is_public FROM models WHERE id = ?', $id));

        $show = $this->controller->handle($this->post(['action' => 'visibility', 'id' => (string) $id, 'is_public' => '1']));
        self::assertSame(1, $this->json($show)['is_public']);

        $stranger = $this->addUser('Stranger');
        $this->loginAs($stranger, 'Stranger');
        self::assertSame(404, $this->controller->handle($this->post(['action' => 'visibility', 'id' => (string) $id, 'is_public' => '0']))->status());
    }
}
