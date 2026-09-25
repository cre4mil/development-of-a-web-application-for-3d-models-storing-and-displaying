<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Produces glTF / GLB / OBJ / USDZ copies of an uploaded model with Blender.
 *
 * Conversion is optional: when BLENDER_PATH is not configured only the original
 * file is registered, so the site keeps working without Blender installed.
 */
final class ModelConverter
{
    private const TARGETS = ['glb', 'obj', 'gltf', 'usdz'];

    /** @var callable(string): mixed */
    private $runner;

    /** @param callable(string): mixed|null $runner executes a shell command; defaults to shell_exec */
    public function __construct(
        private readonly ModelFiles $files,
        private readonly string $blenderPath,
        private readonly string $scriptPath,
        ?callable $runner = null,
    ) {
        $this->runner = $runner ?? 'shell_exec';
    }

    public function enabled(): bool
    {
        return $this->blenderPath !== '' && is_file($this->blenderPath);
    }

    /**
     * @return array{gltf: ?string, glb: ?string, usdz: ?string, obj: ?string} file names by format
     */
    public function convert(string $filename): array
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $result = ['gltf' => null, 'glb' => null, 'usdz' => null, 'obj' => null];
        $outputs = [];
        foreach (self::TARGETS as $target) {
            if ($target === $extension) {
                $result[$target] = $filename;
            } else {
                $outputs[$target] = pathinfo($filename, PATHINFO_FILENAME) . '_converted.' . $target;
            }
        }

        if ($this->enabled()) {
            @set_time_limit(300); // Blender start-up plus export can take a while for large models
            $arguments = array_map(fn (string $name): string => escapeshellarg($this->files->path($name)), $outputs);
            ($this->runner)(sprintf(
                '%s -b -P %s -- %s %s 2>&1',
                escapeshellarg($this->blenderPath),
                escapeshellarg($this->scriptPath),
                escapeshellarg($this->files->path($filename)),
                implode(' ', $arguments)
            ));
            foreach ($outputs as $target => $name) {
                $result[$target] = $this->files->exists($name) ? $name : null;
            }
        }

        return $result;
    }
}
