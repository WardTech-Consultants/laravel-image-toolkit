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

];
