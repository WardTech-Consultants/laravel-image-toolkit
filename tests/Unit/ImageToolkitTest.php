<?php

use Illuminate\Support\Facades\Queue;
use WardTech\ImageToolkit\ImageToolkit;
use WardTech\ImageToolkit\Jobs\OptimizeImage;
use WardTech\ImageToolkit\Models\OptimizedImage;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

    $this->storage = storage_path('app/public');

    if (! is_dir($this->storage)) {
        mkdir($this->storage, 0755, true);
    }

    $this->toolkit = app(ImageToolkit::class);
});

afterEach(function () {
    foreach (glob($this->storage . '/*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
});

function makeImage(string $path, int $width = 400, int $height = 300): string
{
    $img = imagecreatetruecolor($width, $height);
    imagejpeg($img, $path, 90);
    imagedestroy($img);

    return $path;
}

it('normalizes disk-relative paths', function () {
    expect($this->toolkit->normalizePath('/gallery/photo.jpg'))->toBe('gallery/photo.jpg')
        ->and($this->toolkit->normalizePath('../../etc/passwd'))->toBe('etc/passwd')
        ->and($this->toolkit->normalizePath(''))->toBeNull()
        ->and($this->toolkit->normalizePath(null))->toBeNull();
});

it('resolves absolute paths within the disk root', function () {
    makeImage($this->storage . '/photo.jpg');

    expect($this->toolkit->absolutePath('photo.jpg'))->toBe($this->storage . '/photo.jpg')
        ->and($this->toolkit->basePath('public_path'))->toBe(public_path());
});

it('queues an optimization job and tracks the image', function () {
    Queue::fake();

    makeImage($this->storage . '/queued.jpg');

    $record = $this->toolkit->optimize('queued.jpg');

    expect($record)->toBeInstanceOf(OptimizedImage::class)
        ->and($record->path)->toBe('queued.jpg')
        ->and($record->disk)->toBe('public')
        ->and($record->optimized)->toBeFalse();

    Queue::assertPushed(OptimizeImage::class, 1);
});

it('ignores missing files and unsupported extensions', function () {
    Queue::fake();

    file_put_contents($this->storage . '/notes.txt', 'not an image');

    expect($this->toolkit->optimize('does-not-exist.jpg'))->toBeNull()
        ->and($this->toolkit->optimize('notes.txt'))->toBeNull()
        ->and(OptimizedImage::count())->toBe(0);

    Queue::assertNothingPushed();
});

it('skips already optimized images unless forced', function () {
    Queue::fake();

    makeImage($this->storage . '/done.jpg');

    $record = $this->toolkit->optimize('done.jpg');
    $record->update(['optimized' => true]);

    $this->toolkit->optimize('done.jpg');
    Queue::assertPushed(OptimizeImage::class, 1);

    $this->toolkit->optimize('done.jpg', force: true);
    Queue::assertPushed(OptimizeImage::class, 2);

    expect($record->fresh()->optimized)->toBeFalse();
});

it('optimizes many paths at once', function () {
    Queue::fake();

    makeImage($this->storage . '/one.jpg');
    makeImage($this->storage . '/two.jpg');

    $records = $this->toolkit->optimizeMany(['one.jpg', 'two.jpg', null, 'missing.jpg']);

    expect($records)->toHaveCount(2);
    Queue::assertPushed(OptimizeImage::class, 2);
});

it('deletes generated variants but leaves the original alone', function () {
    makeImage($this->storage . '/hero.jpg');
    makeImage($this->storage . '/hero-150.jpg');
    makeImage($this->storage . '/hero-300.jpg');
    makeImage($this->storage . '/hero.webp');
    makeImage($this->storage . '/hero-notes.jpg');

    $deleted = $this->toolkit->deleteVariants('hero.jpg');

    expect($deleted)->toBe(3)
        ->and(file_exists($this->storage . '/hero.jpg'))->toBeTrue()
        ->and(file_exists($this->storage . '/hero-150.jpg'))->toBeFalse()
        ->and(file_exists($this->storage . '/hero-300.jpg'))->toBeFalse()
        ->and(file_exists($this->storage . '/hero.webp'))->toBeFalse()
        // Not a numeric suffix, so not a generated variant.
        ->and(file_exists($this->storage . '/hero-notes.jpg'))->toBeTrue();
});

it('never deletes a webp source as if it were a generated twin', function () {
    $img = imagecreatetruecolor(200, 150);
    imagewebp($img, $this->storage . '/photo.WEBP', 70);
    imagewebp($img, $this->storage . '/photo-150.webp', 70);
    imagedestroy($img);

    // On macOS and Windows "photo.WEBP" and "photo.webp" are one and the same
    // file. This test runs on a case-sensitive filesystem, so a symlink stands
    // in for that: the twin path must resolve to the source and be left alone.
    symlink($this->storage . '/photo.WEBP', $this->storage . '/photo.webp');

    $deleted = $this->toolkit->deleteVariants('photo.WEBP');

    expect(file_exists($this->storage . '/photo.WEBP'))->toBeTrue()
        ->and(is_link($this->storage . '/photo.webp'))->toBeTrue()
        ->and(file_exists($this->storage . '/photo-150.webp'))->toBeFalse()
        ->and($deleted)->toBe(1);
});

it('still removes the webp twin of a jpeg source', function () {
    makeImage($this->storage . '/twin.jpg');

    $img = imagecreatetruecolor(200, 150);
    imagewebp($img, $this->storage . '/twin.webp', 70);
    imagedestroy($img);

    $this->toolkit->deleteVariants('twin.jpg');

    expect(file_exists($this->storage . '/twin.jpg'))->toBeTrue()
        ->and(file_exists($this->storage . '/twin.webp'))->toBeFalse();
});

it('forgets an image, its variants and optionally the original', function () {
    Queue::fake();

    makeImage($this->storage . '/gone.jpg');
    makeImage($this->storage . '/gone-150.jpg');

    $this->toolkit->optimize('gone.jpg');
    expect(OptimizedImage::where('path', 'gone.jpg')->exists())->toBeTrue();

    $this->toolkit->forget('gone.jpg', deleteOriginal: true);

    expect(OptimizedImage::where('path', 'gone.jpg')->exists())->toBeFalse()
        ->and(file_exists($this->storage . '/gone.jpg'))->toBeFalse()
        ->and(file_exists($this->storage . '/gone-150.jpg'))->toBeFalse();
});

it('refuses to resolve paths outside the disk root', function () {
    // The traversal segments are stripped, leaving a path that cannot resolve
    // inside the disk root.
    expect($this->toolkit->absolutePath('../../../etc/passwd'))->toBeNull();
});
