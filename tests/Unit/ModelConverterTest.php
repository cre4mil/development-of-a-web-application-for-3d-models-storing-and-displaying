<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ModelConverter;
use Tests\Support\AppTestCase;

final class ModelConverterTest extends AppTestCase
{
    public function testWithoutBlenderOnlyTheOriginalFormatIsRegistered(): void
    {
        $ran = false;
        $converter = new ModelConverter($this->files(), '', 'convert.py', function () use (&$ran): void {
            $ran = true;
        });

        self::assertFalse($converter->enabled());
        self::assertSame(['gltf' => null, 'glb' => 'a.glb', 'usdz' => null, 'obj' => null], $converter->convert('a.glb'));
        self::assertSame(['gltf' => null, 'glb' => null, 'usdz' => null, 'obj' => 'a.OBJ'], $converter->convert('a.OBJ'));
        self::assertSame(['gltf' => null, 'glb' => null, 'usdz' => null, 'obj' => null], $converter->convert('a.fbx'));
        self::assertFalse($ran);
    }

    public function testMissingBlenderExecutableDisablesConversion(): void
    {
        self::assertFalse((new ModelConverter($this->files(), '/no/such/blender', 'convert.py'))->enabled());
    }

    public function testBlenderIsInvokedAndOnlyProducedFilesAreRegistered(): void
    {
        $blender = $this->uploads . 'blender.exe';
        file_put_contents($blender, '');
        $command = '';
        $converter = new ModelConverter($this->files(), $blender, '/app/convert.py', function (string $cmd) use (&$command): void {
            $command = $cmd;
            // Pretend Blender only managed to write the GLB and OBJ outputs.
            file_put_contents($this->uploads . 'model_converted.glb', 'x');
            file_put_contents($this->uploads . 'model_converted.obj', 'x');
        });

        $result = $converter->convert('model.fbx');

        self::assertTrue($converter->enabled());
        self::assertSame(['gltf' => null, 'glb' => 'model_converted.glb', 'usdz' => null, 'obj' => 'model_converted.obj'], $result);
        self::assertStringContainsString('-b -P', $command);
        self::assertStringContainsString('convert.py', $command);
        self::assertStringContainsString('model.fbx', $command);
        self::assertStringContainsString('model_converted.usdz', $command);
        self::assertStringEndsWith('2>&1', $command);
    }

    public function testOriginalIsKeptWhenConvertingFromAnExportableFormat(): void
    {
        $blender = $this->uploads . 'blender.exe';
        file_put_contents($blender, '');
        $command = '';
        $converter = new ModelConverter($this->files(), $blender, 'convert.py', function (string $cmd) use (&$command): void {
            $command = $cmd;
        });

        $result = $converter->convert('scene.glb');

        self::assertSame('scene.glb', $result['glb']);
        self::assertStringNotContainsString('scene_converted.glb', $command);
        self::assertNull($result['gltf']);
    }
}
