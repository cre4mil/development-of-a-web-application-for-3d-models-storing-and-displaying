<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CatalogRepository;

/** Deletes a model, its database rows and every file on disk. */
final class ModelRemover
{
    public function __construct(private readonly CatalogRepository $catalog, private readonly ModelFiles $files)
    {
    }

    /**
     * @param array<string, mixed> $model
     * @return bool false when the model has sales history and must be kept (hide it instead)
     */
    public function remove(array $model): bool
    {
        if ($this->catalog->hasSales((int) $model['id'])) {
            return false;
        }
        $this->catalog->delete((int) $model['id']);
        $this->files->deleteAll($model);

        return true;
    }
}
