# Laravel Image Toolkit

Image optimization using the Imagify API with responsive WebP `<picture>` Blade component.

## Installation

Add the repository to your `composer.json`:

```json
"repositories": [
    {"type": "vcs", "url": "git@github.com:wardtech/laravel-image-toolkit.git"}
]
```

Then install:

```bash
composer require wardtech/laravel-image-toolkit
```

Run migrations:

```bash
php artisan migrate
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=image-toolkit-config
```

Add your Imagify API key to `.env`:

```
IMAGIFY_API_KEY=your-api-key-here
```

### Config Options

| Key | Default | Description |
|-----|---------|-------------|
| `imagify.api_key` | `env('IMAGIFY_API_KEY')` | Imagify API key |
| `imagify.level` | `aggressive` | Optimization level: normal, aggressive, ultra |
| `imagify.max_width` | `1600` | Max original dimension |
| `sizes` | `[150, 300, 500, 1000]` | Variant widths to generate |
| `quality.jpeg` | `75` | JPEG quality (0-100) |
| `quality.webp` | `70` | WebP quality (0-100) |
| `quality.png` | `8` | PNG compression level (0-9) |
| `scan_paths` | `['public/images', 'storage/app/public']` | Directories to scan |
| `extensions` | `['jpg', 'jpeg', 'png', 'gif', 'webp']` | File types to process |
| `default_disk` | `public` | Disk used by the facade/trait — `public` (`storage/app/public`) or `public_path` (`public/`) |
| `auto_optimize` | `true` | Master switch for the `OptimizesImages` trait |
| `auto_optimize_sync` | `false` | Run runtime optimizations inline instead of queueing them |
| `queue.connection` | `null` | Queue connection for optimization jobs (null = default) |
| `queue.name` | `null` | Queue name for optimization jobs (null = default) |

## Usage

### Artisan Command

```bash
# Queue optimization jobs for all unprocessed images
php artisan images:optimize

# Process synchronously (no queue)
php artisan images:optimize --sync

# Re-process already optimized images
php artisan images:optimize --force
```

### Optimizing Uploads at Runtime

The Artisan command is a sweep. For images uploaded through an admin panel, optimize
them as they arrive with the `ImageToolkit` facade:

```php
use WardTech\ImageToolkit\Facades\ImageToolkit;

// Queue optimization for a freshly uploaded file (path relative to the disk root)
ImageToolkit::optimize('gallery/party.jpg');

// Several at once
ImageToolkit::optimizeMany(['gallery/one.jpg', 'gallery/two.jpg']);

// Re-run for an image that was already optimized
ImageToolkit::optimize('gallery/party.jpg', force: true);

// Run inline instead of queueing
ImageToolkit::optimizeNow('gallery/party.jpg');

// Images stored in public/ rather than storage/app/public
ImageToolkit::optimize('images/hero.jpg', ImageToolkit::DISK_PUBLIC);

// Drop the generated variants and the tracking record (optionally the original too)
ImageToolkit::forget('gallery/party.jpg', deleteOriginal: true);
```

Paths are validated against the disk root, so a traversal attempt never resolves
outside `storage/app/public` (or `public/`).

### Model Trait

Better still, let the model handle it. Add `OptimizesImages` and list the attributes
holding image paths:

```php
use WardTech\ImageToolkit\Concerns\OptimizesImages;

class GalleryImage extends Model
{
    use OptimizesImages;

    protected array $optimizableImages = ['image_path'];
}
```

- On create, every listed image is queued for optimization.
- On update, only images that actually changed are re-optimized; variants belonging
  to a replaced path are deleted.
- On delete, variants **and** the source files are removed. Set
  `protected bool $deleteImageFilesOnDelete = false;` to keep the originals.
- Soft-deleted models keep their files until they are force deleted.

Attributes may hold a single path or an array of paths (e.g. a JSON-cast column).
Override the disk per model with `protected string $optimizableImageDisk = 'public_path';`,
and re-run everything for a record with `$model->optimizeImages(force: true)`.

Set `IMAGE_TOOLKIT_AUTO_OPTIMIZE=false` to switch the trait off (useful while seeding).
Nothing is optimized on save and nothing is deleted on delete — the trait becomes inert.
Set `IMAGE_TOOLKIT_AUTO_OPTIMIZE_SYNC=true` when there is no queue worker running.

### Blade Component

```blade
{{-- Responsive picture with all variants --}}
<x-image-toolkit::picture src="images/hero.jpg" alt="Hero image" />

{{-- With responsive sizes hint --}}
<x-image-toolkit::picture
    src="images/hero.jpg"
    alt="Hero image"
    sizes="(min-width: 1024px) 50vw, 100vw"
/>

{{-- Fixed size variant --}}
<x-image-toolkit::picture src="images/avatar.jpg" alt="Avatar" :size="150" />

{{-- With additional attributes --}}
<x-image-toolkit::picture
    src="images/hero.jpg"
    alt="Hero"
    class="w-full rounded-lg"
    loading="lazy"
    fetchpriority="high"
/>
```

### React Component

Publish the React component:

```bash
php artisan vendor:publish --tag=image-toolkit-react
```

This copies `Picture.jsx` to `resources/js/vendor/image-toolkit/react/`.

```jsx
import Picture from './vendor/image-toolkit/react/Picture';

{/* Responsive picture with all variants */}
<Picture src="images/hero.jpg" alt="Hero image" />

{/* With responsive sizes hint */}
<Picture src="images/hero.jpg" alt="Hero image" sizes="(min-width: 1024px) 50vw, 100vw" />

{/* Fixed size variant */}
<Picture src="images/avatar.jpg" alt="Avatar" size={150} />

{/* With additional attributes */}
<Picture src="images/hero.jpg" alt="Hero" className="w-full rounded-lg" loading="lazy" />

{/* Custom variant widths */}
<Picture src="images/hero.jpg" alt="Hero" widths={[200, 400, 800]} />

{/* Assets served from a CDN */}
<Picture src="images/hero.jpg" alt="Hero" baseUrl="https://cdn.example.com" />
```

### Vue Component

Publish the Vue component:

```bash
php artisan vendor:publish --tag=image-toolkit-vue
```

This copies `Picture.vue` to `resources/js/vendor/image-toolkit/vue/`.

```vue
<script setup>
import Picture from './vendor/image-toolkit/vue/Picture.vue';
</script>

<template>
    <!-- Responsive picture with all variants -->
    <Picture src="images/hero.jpg" alt="Hero image" />

    <!-- With responsive sizes hint -->
    <Picture src="images/hero.jpg" alt="Hero image" sizes="(min-width: 1024px) 50vw, 100vw" />

    <!-- Fixed size variant -->
    <Picture src="images/avatar.jpg" alt="Avatar" :size="150" />

    <!-- With additional attributes -->
    <Picture src="images/hero.jpg" alt="Hero" class="w-full rounded-lg" loading="lazy" />

    <!-- Custom variant widths -->
    <Picture src="images/hero.jpg" alt="Hero" :widths="[200, 400, 800]" />

    <!-- Assets served from a CDN -->
    <Picture src="images/hero.jpg" alt="Hero" base-url="https://cdn.example.com" />
</template>
```

### React/Vue vs Blade

The Blade component checks the filesystem server-side and only includes variants that actually exist. The React and Vue components build URLs based on the naming convention (`filename-{size}.webp`), assuming all variants are present. Browsers handle missing srcset entries gracefully by falling back to the next available source.

### Customizing Views

```bash
php artisan vendor:publish --tag=image-toolkit-views
```

## How It Works

1. **Imagify API** compresses the original image
2. **PHP GD** generates a WebP version of the optimized original
3. **PHP GD** generates resized variants in both original format and WebP for each configured size
4. The `<picture>` Blade component serves WebP sources with srcset, falling back to original format

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- GD extension
- Imagify API key (optional — optimization still generates variants without it)

## License

Proprietary. All rights reserved.
