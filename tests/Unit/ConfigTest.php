<?php

it('loads default config values', function () {
    expect(config('image-toolkit.imagify.level'))->toBe('aggressive');
    expect(config('image-toolkit.imagify.max_width'))->toBe(1600);
    expect(config('image-toolkit.sizes'))->toBe([150, 300, 500, 1000]);
    expect(config('image-toolkit.quality.jpeg'))->toBe(75);
    expect(config('image-toolkit.quality.webp'))->toBe(70);
    expect(config('image-toolkit.quality.png'))->toBe(8);
    expect(config('image-toolkit.extensions'))->toContain('jpg', 'jpeg', 'png');
});

it('respects custom config values', function () {
    config(['image-toolkit.sizes' => [200, 400, 800]]);
    config(['image-toolkit.quality.jpeg' => 85]);

    expect(config('image-toolkit.sizes'))->toBe([200, 400, 800]);
    expect(config('image-toolkit.quality.jpeg'))->toBe(85);
});

it('scan_paths has sensible defaults', function () {
    expect(config('image-toolkit.scan_paths'))->toBe([
        'public/images',
        'storage/app/public',
    ]);
});
