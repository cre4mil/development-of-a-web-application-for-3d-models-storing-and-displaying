<?php

declare(strict_types=1);

/**
 * Rewrites absolute file paths in a PHPUnit clover report to project-relative ones so that
 * SonarQube (which may scan from a different mount point, e.g. Docker) can match them.
 *
 * Usage: php tools/relativize-clover.php [coverage.xml]
 */

$root = str_replace('\\', '/', dirname(__DIR__)) . '/';
$file = $argv[1] ?? dirname(__DIR__) . '/coverage.xml';
if (!is_file($file)) {
    fwrite(STDERR, "Report not found: {$file}\n");
    exit(1);
}

$xml = (string) file_get_contents($file);
$xml = (string) preg_replace_callback(
    '/(<file name=")([^"]+)(")/',
    static function (array $match) use ($root): string {
        $path = str_replace('\\', '/', $match[2]);

        return $match[1] . (str_starts_with($path, $root) ? substr($path, strlen($root)) : $path) . $match[3];
    },
    $xml
);
file_put_contents($file, $xml);
echo "Rewrote clover paths in {$file}\n";
