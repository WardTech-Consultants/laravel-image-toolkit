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
     */
    public function absolutePath(): string
    {
        if ($this->disk === 'public_path') {
            return public_path($this->path);
        }

        return storage_path('app/public/' . $this->path);
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
