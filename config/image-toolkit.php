<?php

return [

    'imagify' => [
        'api_key' => env('IMAGIFY_API_KEY'),
        'level' => 'aggressive',
        'max_width' => 1600,
    ],

    'sizes' => [150, 300, 500, 1000],

    'quality' => [
        'jpeg' => 75,
        'webp' => 70,
        'png' => 8,
    ],

    'scan_paths' => [
        'public/images',
        'storage/app/public',
    ],

    'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],

    /*
    |--------------------------------------------------------------------------
    | Runtime optimization
    |--------------------------------------------------------------------------
    |
    | Used by the ImageToolkit facade and the OptimizesImages model trait, which
    | optimize images as they are uploaded (e.g. from an admin panel) instead of
    | waiting for the next `images:optimize` scan.
    |
    | 'default_disk' is either 'public' (storage/app/public) or 'public_path'
    | (the public/ directory).
    |
    */

    'default_disk' => 'public',

    'auto_optimize' => env('IMAGE_TOOLKIT_AUTO_OPTIMIZE', true),

    'auto_optimize_sync' => env('IMAGE_TOOLKIT_AUTO_OPTIMIZE_SYNC', false),

    'queue' => [
        'connection' => env('IMAGE_TOOLKIT_QUEUE_CONNECTION'),
        'name' => env('IMAGE_TOOLKIT_QUEUE'),
    ],

    'verbose_logging' => env('IMAGE_TOOLKIT_VERBOSE_LOGGING', false),

];
