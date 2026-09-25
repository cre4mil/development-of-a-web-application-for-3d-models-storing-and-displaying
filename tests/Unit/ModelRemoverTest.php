<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\CatalogRepository;
use App\Services\ModelRemover;
use Tests\Support\AppTestCase;

final class ModelRemoverTest extends AppTestCase
{
    public function testRemovesRowsAndFiles(): void
    {
        $owner = $this->addUser('owner');
        $id = $this->addModel($owner, ['filename' => 'a.glb', 'thumb' => 'a.png']);
        file_put_contents($this->uploads . 'a.png', 'x');
        $this->addTags($id, 'chair');
        $this->exec('INSERT INTO likes (user_id, model_id) VALUES (?, ?)', $owner, $id);
        $this->exec('INSERT INTO comments (model_id, user_id, body) VALUES (?, ?, ?)', $id, $owner, 'hi');
        $catalog = new CatalogRepository($this->pdo);

        $removed = (new ModelRemover($catalog, $this->files()))->remove($catalog->find($id));

        self::assertTrue($removed);
        self::assertNull($catalog->find($id));
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM likes'));
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM comments'));
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM model_tags'));
        self::assertSame([], glob($this->uploads . '*'));
    }

    public function testModelsWithSalesAreKept(): void
    {
        $owner = $this->addUser('owner');
        $buyer = $this->addUser('buyer');
        $id = $this->addModel($owner, ['price' => 10]);
        $this->exec("INSERT INTO orders (order_ref, buyer_id, total_amount, payment_status) VALUES ('R1', ?, 10, 'approved')", $buyer);
        $this->exec('INSERT INTO order_items (order_id, model_id, creator_id, price) VALUES (1, ?, ?, 10)', $id, $owner);
        $catalog = new CatalogRepository($this->pdo);

        $removed = (new ModelRemover($catalog, $this->files()))->remove($catalog->find($id));

        self::assertFalse($removed);
        self::assertNotNull($catalog->find($id));
        self::assertCount(1, glob($this->uploads . '*'));
    }
}
