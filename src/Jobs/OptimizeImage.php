<?php

namespace WardTech\ImageToolkit\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use WardTech\ImageToolkit\Models\OptimizedImage;

class OptimizeImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public OptimizedImage $image
    ) {}

    public function handle(): void
    {
        $absolutePath = $this->image->absolutePath();

        if (! file_exists($absolutePath)) {
            Log::warning("ImageToolkit: File not found — {$absolutePath}");
            return;
        }

        $this->cleanupVariants($absolutePath);
        $this->optimizeOriginal($absolutePath);
        $this->generateWebp($absolutePath);
        $this->generateSizedVariants($absolutePath);

        $this->image->update(['optimized' => true]);

        Log::info("ImageToolkit: Completed optimization for {$this->image->path}");
    }

    protected function cleanupVariants(string $absolutePath): void
    {
        $info = pathinfo($absolutePath);
        $dir = $info['dirname'];
        $filename = $info['filename'];

        // Match variant files: filename-{number}.ext and filename-{number}.webp
        $pattern = $dir . '/' . preg_quote($filename, '/');
        $globPattern = $dir . '/' . $filename . '-*';

        foreach (glob($globPattern) as $file) {
            // Only delete files matching the variant naming pattern (filename-123.ext)
            $basename = pathinfo($file, PATHINFO_FILENAME);
            $suffix = str_replace($filename . '-', '', $basename);

            if (ctype_digit($suffix)) {
                unlink($file);
                Log::info("ImageToolkit: Cleaned up old variant {$file}");
            }
        }

        // Also remove the full-size WebP if it exists
        $webpPath = $dir . '/' . $filename . '.webp';
        if ($info['extension'] !== 'webp' && file_exists($webpPath)) {
            unlink($webpPath);
            Log::info("ImageToolkit: Cleaned up old WebP {$webpPath}");
        }
    }

    protected function optimizeOriginal(string $absolutePath): void
    {
        $apiKey = config('image-toolkit.imagify.api_key');

        if (! $apiKey) {
            Log::warning('ImageToolkit: No Imagify API key configured, skipping API optimization.');
            return;
        }

        $originalSize = filesize($absolutePath);
        $this->image->update(['original_size' => $originalSize]);

        $response = Http::withHeaders([
            'Authorization' => 'token ' . $apiKey,
        ])->attach(
            'image', file_get_contents($absolutePath), basename($absolutePath)
        )->post('https://app.imagify.io/api/upload/', [
            'optimization_level' => $this->mapLevel(config('image-toolkit.imagify.level', 'aggressive')),
        ]);

        if ($response->successful()) {
            $optimizedContent = $response->body();
            file_put_contents($absolutePath, $optimizedContent);

            $optimizedSize = filesize($absolutePath);
            $this->image->update(['optimized_size' => $optimizedSize]);

            $savings = round((1 - $optimizedSize / max($originalSize, 1)) * 100, 1);
            Log::info("ImageToolkit: Imagify optimized {$this->image->path} — {$originalSize}B → {$optimizedSize}B ({$savings}% savings)");
        } else {
            Log::error("ImageToolkit: Imagify API error for {$this->image->path} — {$response->status()}: {$response->body()}");
        }
    }

    protected function generateWebp(string $absolutePath): void
    {
        $info = pathinfo($absolutePath);
        $webpPath = $info['dirname'] . '/' . $info['filename'] . '.webp';

        $gdImage = $this->createGdImage($absolutePath);

        if (! $gdImage) {
            Log::warning("ImageToolkit: Could not create GD image from {$absolutePath}");
            return;
        }

        $quality = config('image-toolkit.quality.webp', 70);
        imagewebp($gdImage, $webpPath, $quality);
        imagedestroy($gdImage);

        $relativeWebp = str_replace('\\', '/', $info['filename'] . '.webp');
        $pathDir = dirname($this->image->path);
        $webpRelative = ($pathDir !== '.' ? $pathDir . '/' : '') . $relativeWebp;

        $this->image->update(['webp_path' => $webpRelative]);

        $originalSize = filesize($absolutePath);
        $webpSize = filesize($webpPath);
        Log::info("ImageToolkit: WebP generated for {$this->image->path} — {$originalSize}B → {$webpSize}B");
    }

    protected function generateSizedVariants(string $absolutePath): void
    {
        $sizes = config('image-toolkit.sizes', [150, 300, 500, 1000]);
        $info = pathinfo($absolutePath);
        $extension = strtolower($info['extension'] ?? 'jpg');

        $gdImage = $this->createGdImage($absolutePath);

        if (! $gdImage) {
            return;
        }

        $origWidth = imagesx($gdImage);
        $origHeight = imagesy($gdImage);

        foreach ($sizes as $size) {
            if ($size >= max($origWidth, $origHeight)) {
                continue;
            }

            [$newWidth, $newHeight] = $this->calculateDimensions($origWidth, $origHeight, $size);

            $resized = $this->createResizedImage($gdImage, $newWidth, $newHeight, $origWidth, $origHeight, $extension);

            // Save in original format
            $variantPath = $info['dirname'] . '/' . $info['filename'] . "-{$size}." . $info['extension'];
            $this->saveGdImage($resized, $variantPath, $extension);

            $variantSize = filesize($variantPath);
            Log::info("ImageToolkit: Generated {$size}px variant for {$this->image->path} — {$variantSize}B");

            // Save WebP variant
            $webpVariantPath = $info['dirname'] . '/' . $info['filename'] . "-{$size}.webp";
            $webpQuality = config('image-toolkit.quality.webp', 70);
            imagewebp($resized, $webpVariantPath, $webpQuality);

            $webpVariantSize = filesize($webpVariantPath);
            Log::info("ImageToolkit: Generated {$size}px WebP variant for {$this->image->path} — {$webpVariantSize}B");

            imagedestroy($resized);
        }

        imagedestroy($gdImage);
    }

    protected function createGdImage(string $path): ?\GdImage
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'gif' => @imagecreatefromgif($path),
            'webp' => @imagecreatefromwebp($path),
            default => null,
        };
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function calculateDimensions(int $origWidth, int $origHeight, int $maxDimension): array
    {
        if ($origWidth >= $origHeight) {
            $newWidth = $maxDimension;
            $newHeight = (int) round($origHeight * ($maxDimension / $origWidth));
        } else {
            $newHeight = $maxDimension;
            $newWidth = (int) round($origWidth * ($maxDimension / $origHeight));
        }

        return [$newWidth, $newHeight];
    }

    protected function createResizedImage(
        \GdImage $source,
        int $newWidth,
        int $newHeight,
        int $origWidth,
        int $origHeight,
        string $extension
    ): \GdImage {
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve PNG transparency
        if ($extension === 'png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Preserve GIF transparency
        if ($extension === 'gif') {
            $transparentIndex = imagecolortransparent($source);
            if ($transparentIndex >= 0) {
                $transparentColor = imagecolorsforindex($source, $transparentIndex);
                $transparentNew = imagecolorallocate($resized, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
                imagefill($resized, 0, 0, $transparentNew);
                imagecolortransparent($resized, $transparentNew);
            }
        }

        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        return $resized;
    }

    protected function saveGdImage(\GdImage $image, string $path, string $extension): void
    {
        match ($extension) {
            'jpg', 'jpeg' => imagejpeg($image, $path, config('image-toolkit.quality.jpeg', 75)),
            'png' => imagepng($image, $path, config('image-toolkit.quality.png', 8)),
            'gif' => imagegif($image, $path),
            'webp' => imagewebp($image, $path, config('image-toolkit.quality.webp', 70)),
            default => imagejpeg($image, $path, config('image-toolkit.quality.jpeg', 75)),
        };
    }

    protected function mapLevel(string $level): string
    {
        return match ($level) {
            'normal' => '0',
            'aggressive' => '1',
            'ultra' => '2',
            default => '1',
        };
    }
}
