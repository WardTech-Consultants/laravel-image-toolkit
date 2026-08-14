<?php

namespace WardTech\ImageToolkit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \WardTech\ImageToolkit\Models\OptimizedImage|null optimize(?string $path, ?string $disk = null, bool $force = false, ?bool $sync = null)
 * @method static \Illuminate\Support\Collection optimizeMany(iterable $paths, ?string $disk = null, bool $force = false, ?bool $sync = null)
 * @method static \WardTech\ImageToolkit\Models\OptimizedImage|null optimizeNow(?string $path, ?string $disk = null, bool $force = true)
 * @method static void forget(?string $path, ?string $disk = null, bool $deleteOriginal = false)
 * @method static int deleteVariants(?string $path, ?string $disk = null)
 * @method static int deleteVariantsForAbsolutePath(string $absolutePath, ?string $disk = null)
 * @method static string basePath(?string $disk = null)
 * @method static string|null absolutePath(?string $path, ?string $disk = null)
 * @method static \WardTech\ImageToolkit\Models\OptimizedImage|null record(?string $path)
 * @method static string|null normalizePath(?string $path)
 *
 * @see \WardTech\ImageToolkit\ImageToolkit
 */
class ImageToolkit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \WardTech\ImageToolkit\ImageToolkit::class;
    }
}
