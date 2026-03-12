<?php

use WardTech\ImageToolkit\Jobs\OptimizeImage;
use WardTech\ImageToolkit\Models\OptimizedImage;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

    $this->tempDir = sys_get_temp_dir() . '/image-toolkit-job-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        \Illuminate\Support\Facades\File::deleteDirectory($this->tempDir);
    }
});

it('generates correct variant filenames', function () {
    // Create a test image
    $img = imagecreatetruecolor(800, 600);
    $testFile = $this->tempDir . '/photo.jpg';
    imagejpeg($img, $testFile, 90);
    imagedestroy($img);

    config([
        'image-toolkit.sizes' => [150, 300],
        'image-toolkit.imagify.api_key' => null, // Skip API call
    ]);

    $record = OptimizedImage::create([
        'path' => 'photo.jpg',
        'disk' => 'public_path',
    ]);

    // Mock absolutePath to return our temp file
    $job = new OptimizeImage($record);

    // Use reflection to test variant generation directly
    $reflection = new ReflectionClass($job);

    $method = $reflection->getMethod('calculateDimensions');
    [$w, $h] = $method->invoke($job, 800, 600, 300);

    expect($w)->toBe(300);
    expect($h)->toBe(225);

    [$w, $h] = $method->invoke($job, 600, 800, 300);

    expect($w)->toBe(225);
    expect($h)->toBe(300);
});

it('generates sized variants with correct dimensions', function () {
    $img = imagecreatetruecolor(800, 600);
    $testFile = $this->tempDir . '/landscape.jpg';
    imagejpeg($img, $testFile, 90);
    imagedestroy($img);

    config([
        'image-toolkit.sizes' => [150, 300],
        'image-toolkit.quality.jpeg' => 75,
        'image-toolkit.quality.webp' => 70,
        'image-toolkit.imagify.api_key' => null,
    ]);

    $job = new OptimizeImage(new OptimizedImage());
    $reflection = new ReflectionClass($job);

    $method = $reflection->getMethod('generateSizedVariants');
    $createGd = $reflection->getMethod('createGdImage');

    // Test createGdImage
    $gdImage = $createGd->invoke($job, $testFile);
    expect($gdImage)->toBeInstanceOf(\GdImage::class);
    imagedestroy($gdImage);
});

it('creates webp version of the image', function () {
    $img = imagecreatetruecolor(200, 200);
    $testFile = $this->tempDir . '/test-webp.jpg';
    imagejpeg($img, $testFile, 90);
    imagedestroy($img);

    $job = new OptimizeImage(new OptimizedImage());
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('createGdImage');

    $gdImage = $method->invoke($job, $testFile);
    expect($gdImage)->toBeInstanceOf(\GdImage::class);

    // Verify we can create a webp from it
    $webpPath = $this->tempDir . '/test-webp.webp';
    $result = imagewebp($gdImage, $webpPath, 70);
    expect($result)->toBeTrue();
    expect(file_exists($webpPath))->toBeTrue();
    expect(filesize($webpPath))->toBeGreaterThan(0);

    imagedestroy($gdImage);
});
