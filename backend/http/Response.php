<?php

declare(strict_types=1);

namespace App\Http;

/** A fully-built HTTP response; nothing is emitted until send(). */
final class Response
{
    /** @param array<string, string> $headers */
    private function __construct(
        private readonly int $status,
        private readonly array $headers,
        private readonly string $body,
        private readonly ?string $file = null,
    ) {
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            $status,
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'],
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
        );
    }

    public static function text(string $text, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/plain; charset=utf-8'], $text);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self($status, ['Location' => $location], '');
    }

    /** Streams a file from disk as an attachment. */
    public static function download(string $path, string $downloadName): self
    {
        $safeName = str_replace(['"', "\r", "\n", '\\'], '', $downloadName);

        return new self(200, [
            'Content-Description' => 'File Transfer',
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $safeName . '"',
            'Content-Length' => (string) filesize($path),
        ], '', $path);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /** @return array<string, mixed>|null decoded JSON body */
    public function data(): ?array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function send(): void
    {
        @http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            @header($name . ': ' . $value);
        }
        echo $this->body;
        if ($this->file !== null) {
            readfile($this->file);
        }
    }
}
