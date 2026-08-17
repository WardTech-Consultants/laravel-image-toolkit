<?php

namespace WardTech\ImageToolkit\Concerns;

use Illuminate\Database\Eloquent\Model;
use WardTech\ImageToolkit\Facades\ImageToolkit;

/**
 * Keeps a model's uploaded images optimized.
 *
 * Add the trait to any model that stores image paths, and list the attributes
 * holding them:
 *
 *     class GalleryImage extends Model
 *     {
 *         use OptimizesImages;
 *
 *         protected array $optimizableImages = ['image_path'];
 *     }
 *
 * On save, changed images are queued for optimization. On delete, the
 * generated variants (and, by default, the source file) are removed. Setting
 * `image-toolkit.auto_optimize` to false disables both, leaving files untouched.
 *
 * Attributes may hold a single path or an array of paths (e.g. a JSON cast).
 */
trait OptimizesImages
{
    public static function bootOptimizesImages(): void
    {
        static::created(function (Model $model): void {
            if (! config('image-toolkit.auto_optimize', true)) {
                return;
            }

            $model->optimizeImages(onlyChanged: false);
        });

        static::updated(function (Model $model): void {
            if (! config('image-toolkit.auto_optimize', true)) {
                return;
            }

            $model->optimizeImages();
        });

        static::deleted(function (Model $model): void {
            // The trait is off entirely, so it must not delete anything either.
            if (! config('image-toolkit.auto_optimize', true)) {
                return;
            }

            // Leave files alone for soft deletes — they come back on restore.
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->purgeOptimizedImages();
        });
    }

    /**
     * Attributes holding optimizable image paths.
     *
     * @return array<int, string>
     */
    public function optimizableImageAttributes(): array
    {
        return property_exists($this, 'optimizableImages') ? $this->optimizableImages : [];
    }

    /**
     * The image-toolkit disk these paths are relative to.
     */
    public function optimizableImageDisk(): string
    {
        return property_exists($this, 'optimizableImageDisk')
            ? $this->optimizableImageDisk
            : config('image-toolkit.default_disk', 'public');
    }

    /**
     * Whether deleting the model should delete its image files too.
     */
    public function shouldDeleteImageFilesOnDelete(): bool
    {
        return property_exists($this, 'deleteImageFilesOnDelete')
            ? (bool) $this->deleteImageFilesOnDelete
            : true;
    }

    /**
     * Queue optimization for the model's images.
     *
     * @param  bool  $force  Re-optimize images already marked as optimized.
     * @param  bool|null  $onlyChanged  Limit to attributes changed by the last save.
     *                                  Defaults to true unless forcing.
     */
    public function optimizeImages(bool $force = false, ?bool $onlyChanged = null): void
    {
        $onlyChanged ??= ! $force;
        $disk = $this->optimizableImageDisk();

        foreach ($this->optimizableImageAttributes() as $attribute) {
            $changed = $this->wasChanged($attribute);

            if ($onlyChanged && ! $changed) {
                continue;
            }

            if ($changed) {
                $this->cleanUpReplacedImages($attribute, $disk);
            }

            ImageToolkit::optimizeMany($this->imagePathsFor($attribute), $disk, $force);
        }
    }

    /**
     * Remove generated variants (and optionally the originals) for this model.
     */
    public function purgeOptimizedImages(?bool $deleteOriginals = null): void
    {
        $deleteOriginals ??= $this->shouldDeleteImageFilesOnDelete();
        $disk = $this->optimizableImageDisk();

        foreach ($this->optimizableImageAttributes() as $attribute) {
            foreach ($this->imagePathsFor($attribute) as $path) {
                ImageToolkit::forget($path, $disk, $deleteOriginals);
            }
        }
    }

    /**
     * Paths held by an attribute, normalised to a flat list.
     *
     * @return array<int, string>
     */
    protected function imagePathsFor(string $attribute, bool $original = false): array
    {
        $value = $original ? $this->getOriginal($attribute) : $this->getAttribute($attribute);

        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }

        return collect($value)
            ->flatten()
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->values()
            ->all();
    }

    /**
     * Drop variants belonging to image paths this model no longer references.
     * The source files themselves are left to whatever removed them (Filament's
     * file upload field deletes them on save).
     */
    protected function cleanUpReplacedImages(string $attribute, string $disk): void
    {
        $current = $this->imagePathsFor($attribute);
        $previous = $this->imagePathsFor($attribute, original: true);

        foreach (array_diff($previous, $current) as $path) {
            ImageToolkit::forget($path, $disk);
        }
    }
}
