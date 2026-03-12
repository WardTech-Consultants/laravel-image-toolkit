<?php

namespace WardTech\ImageToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;
use WardTech\ImageToolkit\Jobs\OptimizeImage;
use WardTech\ImageToolkit\Models\OptimizedImage;

class OptimizeImages extends Command
{
    protected $signature = 'images:optimize {--sync : Process images synchronously} {--force : Re-process already optimized images}';

    protected $description = 'Scan configured paths and optimize images using Imagify API';

    public function handle(): int
    {
        $scanPaths = config('image-toolkit.scan_paths', []);
        $extensions = config('image-toolkit.extensions', []);

        $files = $this->discoverImages($scanPaths, $extensions);

        if ($files->isEmpty()) {
            $this->info('No images found in configured scan paths.');
            return self::SUCCESS;
        }

        $this->info("Found {$files->count()} image(s) to process.");

        $bar = $this->output->createProgressBar($files->count());
        $bar->start();

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($files as $file) {
            try {
                $relativePath = $file['relative'];
                $disk = $file['disk'];

                $record = OptimizedImage::firstOrCreate(
                    ['path' => $relativePath],
                    ['disk' => $disk]
                );

                if ($record->optimized && ! $this->option('force')) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                if ($this->option('force')) {
                    $record->update(['optimized' => false]);
                }

                if ($this->option('sync')) {
                    dispatch_sync(new OptimizeImage($record));
                } else {
                    dispatch(new OptimizeImage($record));
                }

                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->error("Failed: {$file['relative']} — {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Processed', 'Skipped', 'Failed'],
            [[$processed, $skipped, $failed]]
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{absolute: string, relative: string, disk: string}>
     */
    protected function discoverImages(array $scanPaths, array $extensions): \Illuminate\Support\Collection
    {
        $files = collect();
        $pattern = '/\.(' . implode('|', $extensions) . ')$/i';

        foreach ($scanPaths as $scanPath) {
            $absolutePath = $this->resolveAbsolutePath($scanPath);
            $disk = $this->determineDisk($scanPath);

            if (! File::isDirectory($absolutePath)) {
                continue;
            }

            $finder = Finder::create()
                ->files()
                ->in($absolutePath)
                ->name($pattern)
                ->sortByName();

            foreach ($finder as $file) {
                $relativePath = $this->relativizePath($file->getPathname(), $scanPath, $disk);

                $files->push([
                    'absolute' => $file->getPathname(),
                    'relative' => $relativePath,
                    'disk' => $disk,
                ]);
            }
        }

        return $files;
    }

    protected function resolveAbsolutePath(string $scanPath): string
    {
        if (str_starts_with($scanPath, 'storage/')) {
            return base_path($scanPath);
        }

        return base_path($scanPath);
    }

    protected function determineDisk(string $scanPath): string
    {
        if (str_starts_with($scanPath, 'storage/app/public')) {
            return 'public';
        }

        return 'public_path';
    }

    protected function relativizePath(string $absolutePath, string $scanPath, string $disk): string
    {
        $basePath = base_path($scanPath);
        $relative = str_replace('\\', '/', ltrim(str_replace($basePath, '', $absolutePath), '/\\'));

        if ($disk === 'public_path') {
            // For public_path, store path relative to public/
            $publicPrefix = str_replace('public/', '', $scanPath);
            return $publicPrefix . '/' . $relative;
        }

        // For storage disk, store path relative to storage/app/public/
        $storagePrefix = str_replace('storage/app/public/', '', $scanPath . '/');
        $storagePrefix = rtrim($storagePrefix, '/');

        return $storagePrefix ? $storagePrefix . '/' . $relative : $relative;
    }
}
