<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use WardTech\ImageToolkit\Concerns\OptimizesImages;
use WardTech\ImageToolkit\Jobs\OptimizeImage;
use WardTech\ImageToolkit\Models\OptimizedImage;

class Photo extends Model
{
    use OptimizesImages;

    protected $table = 'photos';

    protected $guarded = [];

    protected $casts = ['extra_images' => 'array'];

    protected array $optimizableImages = ['image_path', 'extra_images'];
}

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

    Schema::create('photos', function ($table) {
        $table->id();
        $table->string('image_path')->nullable();
        $table->json('extra_images')->nullable();
        $table->timestamps();
    });

    $this->storage = storage_path('app/public');

    if (! is_dir($this->storage)) {
        mkdir($this->storage, 0755, true);
    }
});

afterEach(function () {
    foreach (glob($this->storage . '/*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    Schema::dropIfExists('photos');
});

function makePhotoFile(string $path): string
{
    $img = imagecreatetruecolor(200, 150);
    imagejpeg($img, $path, 90);
    imagedestroy($img);

    return $path;
}

it('optimizes images when a model is created', function () {
    Queue::fake();

    makePhotoFile($this->storage . '/one.jpg');
    makePhotoFile($this->storage . '/two.jpg');

    Photo::create([
        'image_path' => 'one.jpg',
        'extra_images' => ['two.jpg'],
    ]);

    Queue::assertPushed(OptimizeImage::class, 2);
    expect(OptimizedImage::count())->toBe(2);
});

it('only re-optimizes attributes that changed', function () {
    Queue::fake();

    makePhotoFile($this->storage . '/first.jpg');
    makePhotoFile($this->storage . '/second.jpg');

    $photo = Photo::create(['image_path' => 'first.jpg']);
    Queue::assertPushed(OptimizeImage::class, 1);

    $photo->update(['updated_at' => now()->addMinute()]);
    Queue::assertPushed(OptimizeImage::class, 1);

    $photo->update(['image_path' => 'second.jpg']);
    Queue::assertPushed(OptimizeImage::class, 2);
});

it('cleans up variants of a replaced image but keeps the file', function () {
    Queue::fake();

    makePhotoFile($this->storage . '/old.jpg');
    makePhotoFile($this->storage . '/old-150.jpg');
    makePhotoFile($this->storage . '/new.jpg');

    $photo = Photo::create(['image_path' => 'old.jpg']);
    $photo->update(['image_path' => 'new.jpg']);

    expect(file_exists($this->storage . '/old-150.jpg'))->toBeFalse()
        ->and(file_exists($this->storage . '/old.jpg'))->toBeTrue()
        ->and(OptimizedImage::where('path', 'old.jpg')->exists())->toBeFalse()
        ->and(OptimizedImage::where('path', 'new.jpg')->exists())->toBeTrue();
});

it('deletes image files and variants when the model is deleted', function () {
    Queue::fake();

    makePhotoFile($this->storage . '/doomed.jpg');
    makePhotoFile($this->storage . '/doomed-300.jpg');
    makePhotoFile($this->storage . '/extra.jpg');

    $photo = Photo::create([
        'image_path' => 'doomed.jpg',
        'extra_images' => ['extra.jpg'],
    ]);

    $photo->delete();

    expect(file_exists($this->storage . '/doomed.jpg'))->toBeFalse()
        ->and(file_exists($this->storage . '/doomed-300.jpg'))->toBeFalse()
        ->and(file_exists($this->storage . '/extra.jpg'))->toBeFalse()
        ->and(OptimizedImage::count())->toBe(0);
});

it('respects the auto_optimize config switch', function () {
    Queue::fake();

    config(['image-toolkit.auto_optimize' => false]);

    makePhotoFile($this->storage . '/quiet.jpg');

    Photo::create(['image_path' => 'quiet.jpg']);

    Queue::assertNothingPushed();
    expect(OptimizedImage::count())->toBe(0);
});
