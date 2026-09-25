<?php

declare(strict_types=1);

namespace App\Http;

/** View of the current HTTP request. */
final class Request
{
    private readonly Session $session;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     * @param array<string, mixed> $sessionData session storage, held by reference
     */
    public function __construct(
        private readonly array $query = [],
        private readonly array $post = [],
        private readonly array $files = [],
        private readonly array $server = [],
        array &$sessionData = [],
    ) {
        $this->session = new Session($sessionData);
    }

    public static function fromGlobals(): self
    {
        $_SESSION ??= [];

        return new self($_GET, $_POST, $_FILES, $_SERVER, $_SESSION);
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function uri(): string
    {
        return (string) ($this->server['REQUEST_URI'] ?? '/');
    }

    /** Current page as "file.php?query", without the one-shot login flag; used as a post-login return target. */
    public function page(): string
    {
        $file = basename((string) parse_url($this->uri(), PHP_URL_PATH)) ?: 'index.php';
        $query = http_build_query(array_diff_key($this->query, ['login' => true]));

        return $query === '' ? $file : $file . '?' . $query;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /** POST value first, then query string. */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->string($key, (string) $default);
    }

    /** @return array<int|string, mixed> */
    public function list(string $key): array
    {
        $value = $this->input($key, []);

        return is_array($value) ? $value : [];
    }

    /** @return array<string, mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return isset($this->server[$key]) ? (string) $this->server[$key] : null;
    }

    /** True when the body exceeded post_max_size, which makes PHP drop $_POST and $_FILES. */
    public function bodyWasDiscarded(): bool
    {
        return $this->post === [] && $this->files === [] && (int) ($this->server['CONTENT_LENGTH'] ?? 0) > 0;
    }

    public function session(): Session
    {
        return $this->session;
    }

    public function csrfToken(): ?string
    {
        $token = $this->header('X-CSRF-Token') ?? $this->post('csrf');

        return is_string($token) ? $token : null;
    }

    public function hasValidCsrf(): bool
    {
        return $this->session->isValidCsrf($this->csrfToken());
    }
}
