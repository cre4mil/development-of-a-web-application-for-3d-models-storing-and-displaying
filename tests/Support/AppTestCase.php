<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Http\Request;
use App\Http\Response;
use App\Services\ModelFiles;
use App\Support\Config;
use App\Support\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that need the database, an upload directory and request helpers.
 *
 * Every test gets a fresh in-memory SQLite database and a private temporary uploads folder.
 */
abstract class AppTestCase extends TestCase
{
    protected PDO $pdo;
    protected string $uploads;
    /** @var array<string, mixed> */
    protected array $session = [];

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec((string) file_get_contents(__DIR__ . '/schema.sql'));
        Database::use($this->pdo);

        $this->uploads = sys_get_temp_dir() . '/3dg-tests-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->uploads, 0777, true);
        Config::set('UPLOAD_DIR', $this->uploads);
        $this->session = [];
    }

    protected function tearDown(): void
    {
        Database::use(null);
        Config::set('UPLOAD_DIR', null);
        $this->removeDirectory($this->uploads);
    }

    private function removeDirectory(string $directory): void
    {
        foreach (glob($directory . '*', GLOB_NOSORT) ?: [] as $entry) {
            is_dir($entry) ? $this->removeDirectory($entry . '/') : unlink($entry);
        }
        rmdir($directory);
    }

    /** ModelFiles that "moves" uploads with copy(), since move_uploaded_file() only accepts real HTTP uploads. */
    protected function files(): ModelFiles
    {
        return new ModelFiles($this->uploads, static fn (string $from, string $to): bool => copy($from, $to));
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    protected function request(array $query = [], array $post = [], array $files = [], array $server = []): Request
    {
        return new Request($query, $post, $files, $server + ['REQUEST_METHOD' => $post === [] ? 'GET' : 'POST'], $this->session);
    }

    /**
     * POST request carrying a valid CSRF token.
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     */
    protected function post(array $post = [], array $files = [], array $query = []): Request
    {
        $this->session['csrf'] ??= 'test-csrf-token';

        return new Request($query, $post + ['csrf' => $this->session['csrf']], $files, ['REQUEST_METHOD' => 'POST'], $this->session);
    }

    protected function loginAs(int $id, string $name = 'user', bool $admin = false): void
    {
        $this->session['uid'] = $id;
        $this->session['uname'] = $name;
        $this->session['is_admin'] = $admin ? 1 : 0;
    }

    protected function addUser(string $name = 'alice', bool $admin = false, string $password = 'secret'): int
    {
        $this->pdo->prepare('INSERT INTO users (username, email, password, is_admin) VALUES (?, ?, ?, ?)')
            ->execute([$name, strtolower($name) . '@gmail.com', password_hash($password, PASSWORD_DEFAULT, ['cost' => 4]), $admin ? 1 : 0]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Inserts a model and (unless $writeFile is false) a matching file in the uploads folder.
     *
     * @param array<string, mixed> $overrides
     */
    protected function addModel(int $userId, array $overrides = [], bool $writeFile = true): int
    {
        $row = $overrides + [
            'user_id' => $userId,
            'title' => 'Model ' . bin2hex(random_bytes(2)),
            'filename' => bin2hex(random_bytes(4)) . '.glb',
            'thumb' => '',
            'description' => 'A description',
            'is_public' => 1,
            'license' => 'CC BY',
            'price' => 0,
            'view_count' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $this->pdo->prepare('INSERT INTO models (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')')
            ->execute(array_values($row));
        if ($writeFile) {
            file_put_contents($this->uploads . $row['filename'], 'glTF-test-data');
        }

        return (int) $this->pdo->lastInsertId();
    }

    protected function addTags(int $modelId, string ...$names): void
    {
        foreach ($names as $name) {
            $this->pdo->prepare('INSERT OR IGNORE INTO tags (name) VALUES (?)')->execute([$name]);
            $tagId = (int) $this->pdo->query("SELECT id FROM tags WHERE name = '{$name}'")->fetchColumn();
            $this->pdo->prepare('INSERT INTO model_tags (model_id, tag_id) VALUES (?, ?)')->execute([$modelId, $tagId]);
        }
    }

    protected function exec(string $sql, mixed ...$params): void
    {
        $this->pdo->prepare($sql)->execute($params);
    }

    protected function scalar(string $sql, mixed ...$params): mixed
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn();
    }

    /** Creates a temporary "uploaded" file and returns its $_FILES entry. @return array<string, mixed> */
    protected function upload(string $name, string $content = 'data', int $error = UPLOAD_ERR_OK): array
    {
        $path = tempnam(sys_get_temp_dir(), '3dg');
        file_put_contents($path, $content);

        return ['name' => $name, 'tmp_name' => $path, 'error' => $error, 'size' => strlen($content), 'type' => 'application/octet-stream'];
    }

    /** @return array<string, mixed> decoded JSON body of a response */
    protected function json(Response $response): array
    {
        return $response->data() ?? [];
    }
}
