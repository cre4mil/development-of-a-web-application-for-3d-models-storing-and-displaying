<?php

declare(strict_types=1);

use App\Support\Config;
use App\Support\Format;

if (!function_exists('e')) {
    /** HTML-escapes a value for output. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('asset')) {
    /** URL of a file under frontend/assets, cache-busted by modification time. */
    function asset(string $path): string
    {
        $file = Config::root() . '/frontend/assets/' . $path;

        return 'assets/' . $path . '?v=' . (is_file($file) ? filemtime($file) : 0);
    }
}

if (!function_exists('money')) {
    function money(int|float|string $amount): string
    {
        return Format::money($amount);
    }
}

if (!function_exists('partial')) {
    /**
     * Renders frontend/templates/partials/{name}.php.
     *
     * @param array<string, mixed> $data
     */
    function partial(string $name, array $data = []): string
    {
        return App\Http\View::render('partials/' . $name, $data);
    }
}

if (!function_exists('uploadUrl')) {
    /** Public URL of an uploaded file, or the placeholder when the name is empty. */
    function uploadUrl(?string $filename): string
    {
        return $filename === null || $filename === '' ? 'assets/img/placeholder.svg' : 'uploads/' . rawurlencode($filename);
    }
}
