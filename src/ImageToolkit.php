<?php

namespace WardTech\ImageToolkit;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use WardTech\ImageToolkit\Jobs\OptimizeImage;
use WardTech\ImageToolkit\Models\OptimizedImage;

/**
 * Entry point for optimizing images at runtime — e.g. straight after an admin
 * panel upload — rather than waiting for the scheduled `images:optimize` scan.
 */
class ImageToolkit
{
    /** Images living under storage/app/public (the "public" filesystem disk). */
    public const DISK_STORAGE = 'public';

    /** Images living under public/. */
    public const DISK_PUBLIC = 'public_path';

    /**
     * Queue (or run) optimization for a single image path.
     *
     * @param  string  $path  Path relative to the disk root, e.g. "gallery/photo.jpg".
     * @param  string|null  $disk  One of self::DISK_STORAGE / self::DISK_PUBLIC.
     */
    public function optimize(
        ?string $path,
        ?string $disk = null,
        bool $force = false,
        ?bool $sync = null
    ): ?OptimizedImage {
        $path = $this->normalizePath($path);

        if ($path === null) {
            return null;
        }

        $disk = $this->normalizeDisk($disk);
        $absolute = $this->absolutePath($path, $disk);

        if ($absolute === null || ! is_file($absolute)) {
            Log::warning("ImageToolkit: Cannot optimize missing file — {$path} ({$disk})");

            return null;
        }

        if (! $this->hasSupportedExtension($path)) {
            return null;
        }

        $record = OptimizedImage::firstOrNew(['path' => $path]);
        $record->disk = $disk;
        $record->save();

        if ($record->optimized && ! $force) {
            return $record;
        }

        if ($record->optimized) {
            $record->update(['optimized' => false]);
        }

        $this->dispatchJob($record, $sync ?? (bool) config('image-toolkit.auto_optimize_sync', false));

        return $record;
    }

    /**
     * Queue (or run) optimization for several image paths.
     *
     * @param  iterable<int, string|null>  $paths
     * @return Collection<int, OptimizedImage>
     */
    public function optimizeMany(
        iterable $paths,
        ?string $disk = null,
        bool $force = false,
        ?bool $sync = null
    ): Collection {
        $records = collect();

        foreach ($paths as $path) {
            $record = $this->optimize($path, $disk, $force, $sync);

            if ($record) {
                $records->push($record);
            }
        }

        return $records;
    }

    /**
     * Optimize an image immediately, without going through the queue.
     */
    public function optimizeNow(?string $path, ?string $disk = null, bool $force = true): ?OptimizedImage
    {
        return $this->optimize($path, $disk, $force, true);
    }

    /**
     * Remove every generated variant for an image and drop its tracking record.
     *
     * @param  bool  $deleteOriginal  Also delete the source image itself.
     */
    public function forget(?string $path, ?string $disk = null, bool $deleteOriginal = false): void
    {
        $path = $this->normalizePath($path);

        if ($path === null) {
            return;
        }

        $disk = $this->normalizeDisk($disk);

        $this->deleteVariants($path, $disk);

        if ($deleteOriginal) {
            $absolute = $this->absolutePath($path, $disk);

            if ($absolute !== null && is_file($absolute)) {
                @unlink($absolute);
            }
        }

        OptimizedImage::where('path', $path)->delete();
    }

    /**
     * Delete the WebP twin and every sized variant generated for an image.
     *
     * @return int Number of files deleted.
     */
    public function deleteVariants(?string $path, ?string $disk = null): int
    {
        $path = $this->normalizePath($path);

        if ($path === null) {
            return 0;
        }

        $absolute = $this->absolutePath($path, $this->normalizeDisk($disk));

        if ($absolute === null) {
            return 0;
        }

        return $this->deleteVariantsForAbsolutePath($absolute, $this->normalizeDisk($disk));
    }

    /**
     * Delete variants given an already-resolved absolute path. Used by the
     * optimization job so both paths share one boundary-checked implementation.
     *
     * @return int Number of files deleted.
     */
    public function deleteVariantsForAbsolutePath(string $absolutePath, ?string $disk = null): int
    {
        $info = pathinfo($absolutePath);
        $dir = $info['dirname'] ?? null;
        $filename = $info['filename'] ?? null;

        if (! $dir || $filename === null || $filename === '') {
            return 0;
        }

        $realBase = realpath($this->basePath($this->normalizeDisk($disk)));
        $realDir = realpath($dir);

        if (! $realBase || ! $realDir || ! str_starts_with($realDir, $realBase)) {
            Log::warning("ImageToolkit: Variant cleanup skipped — directory outside expected boundary: {$dir}");

            return 0;
        }

        $deleted = 0;

        foreach (glob($dir . '/' . $filename . '-*') ?: [] as $file) {
            $suffix = str_replace($filename . '-', '', pathinfo($file, PATHINFO_FILENAME));

            if (ctype_digit($suffix) && is_file($file)) {
                @unlink($file);
                $deleted++;
            }
        }

        $webpPath = $dir . '/' . $filename . '.webp';

        if (($info['extension'] ?? null) !== 'webp' && is_file($webpPath)) {
            @unlink($webpPath);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Absolute filesystem root for a disk.
     */
    public function basePath(?string $disk = null): string
    {
        return $this->normalizeDisk($disk) === self::DISK_PUBLIC
            ? public_path()
            : storage_path('app/public');
    }

    /**
     * Resolve a disk-relative path to an absolute one, or null if it escapes the disk root.
     */
    public function absolutePath(?string $path, ?string $disk = null): ?string
    {
        $path = $this->normalizePath($path);

        if ($path === null) {
            return null;
        }

        $base = $this->basePath($disk);
        $candidate = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $path;

        $realBase = realpath($base);
        $realDir = realpath(dirname($candidate));

        if (! $realBase || ! $realDir || ! str_starts_with($realDir, $realBase)) {
            return null;
        }

        return $realDir . DIRECTORY_SEPARATOR . basename($candidate);
    }

    /**
     * Tracking record for a path, if one exists.
     */
    public function record(?string $path): ?OptimizedImage
    {
        $path = $this->normalizePath($path);

        return $path === null ? null : OptimizedImage::where('path', $path)->first();
    }

    /**
     * Strip leading slashes and traversal segments from a disk-relative path.
     */
    public function normalizePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));
        $path = str_replace(['../', './'], '', $path);
        $path = ltrim($path, '/');

        return $path === '' ? null : $path;
    }

    protected function normalizeDisk(?string $disk): string
    {
        $disk ??= config('image-toolkit.default_disk', self::DISK_STORAGE);

        return $disk === self::DISK_PUBLIC ? self::DISK_PUBLIC : self::DISK_STORAGE;
    }

    protected function hasSupportedExtension(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $supported = array_map('strtolower', config('image-toolkit.extensions', []));

        if (in_array($extension, $supported, true)) {
            return true;
        }

        Log::info("ImageToolkit: Skipping unsupported file type — {$path}");

        return false;
    }

    protected function dispatchJob(OptimizedImage $record, bool $sync): void
    {
        $job = new OptimizeImage($record);

        if ($sync) {
            dispatch_sync($job);

            return;
        }

        if ($connection = config('image-toolkit.queue.connection')) {
            $job->onConnection($connection);
        }

        if ($queue = config('image-toolkit.queue.name')) {
            $job->onQueue($queue);
        }

        dispatch($job);
    }
}
