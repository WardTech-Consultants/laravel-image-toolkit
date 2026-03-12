<?php

namespace WardTech\ImageToolkit\Models;

use Illuminate\Database\Eloquent\Model;

class OptimizedImage extends Model
{
    protected $fillable = [
        'path',
        'disk',
        'original_size',
        'optimized_size',
        'webp_path',
        'optimized',
    ];

    protected function casts(): array
    {
        return [
            'original_size' => 'integer',
            'optimized_size' => 'integer',
            'optimized' => 'boolean',
        ];
    }

    /**
     * Get the absolute path to this image on disk.
     *
     * @throws \RuntimeException If the resolved path escapes the expected base directory.
     */
    public function absolutePath(): string
    {
        $base = $this->disk === 'public_path' ? public_path() : storage_path('app/public');
        $candidate = $base . DIRECTORY_SEPARATOR . $this->path;

        $realBase = realpath($base);
        $realCandidate = realpath(dirname($candidate)) . DIRECTORY_SEPARATOR . basename($candidate);

        if (! $realBase || ! str_starts_with($realCandidate, $realBase)) {
            throw new \RuntimeException("Path traversal detected: {$this->path}");
        }

        return $candidate;
    }

    /**
     * Get the URL-accessible path for this image.
     */
    public function url(): string
    {
        if ($this->disk === 'public_path') {
            return asset($this->path);
        }

        return asset('storage/' . $this->path);
    }
}
