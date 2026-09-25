<?php

declare(strict_types=1);

namespace App\Http;

/** Thin wrapper around a session array (normally $_SESSION, held by reference). */
final class Session
{
    /** @param array<string, mixed> $data */
    public function __construct(private array &$data)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    /** Reads a value and removes it (flash messages). */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->data[$key] ?? $default;
        unset($this->data[$key]);

        return $value;
    }

    public function userId(): int
    {
        return (int) ($this->data['uid'] ?? 0);
    }

    public function username(): string
    {
        return (string) ($this->data['uname'] ?? '');
    }

    public function isAdmin(): bool
    {
        return $this->userId() > 0 && (int) ($this->data['is_admin'] ?? 0) === 1;
    }

    public function login(int $id, string $username, bool $isAdmin): void
    {
        $this->data['uid'] = $id;
        $this->data['uname'] = $username;
        $this->data['is_admin'] = $isAdmin ? 1 : 0;
        @session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $this->data = [];
        $params = session_get_cookie_params();
        @setcookie(session_name(), '', time() - 42000, $params['path'] ?: '/', $params['domain'], $params['secure'], $params['httponly']);
        @session_destroy();
    }

    public function csrf(): string
    {
        if (empty($this->data['csrf'])) {
            $this->data['csrf'] = bin2hex(random_bytes(16));
        }

        return (string) $this->data['csrf'];
    }

    public function isValidCsrf(?string $submitted): bool
    {
        $expected = (string) ($this->data['csrf'] ?? '');

        return $submitted !== null && $submitted !== '' && $expected !== '' && hash_equals($expected, $submitted);
    }
}
