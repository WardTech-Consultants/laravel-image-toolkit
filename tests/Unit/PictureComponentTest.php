<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
});

it('renders picture tag with fallback when no variants exist', function () {
    $html = Blade::render('<x-image-toolkit::picture src="images/hero.jpg" alt="Hero" />');

    expect($html)->toContain('<picture>');
    expect($html)->toContain('alt="Hero"');
    expect($html)->toContain('<img');
    expect($html)->toContain('hero.jpg');
});

it('renders picture tag with size prop', function () {
    $html = Blade::render('<x-image-toolkit::picture src="images/hero.jpg" alt="Hero" :size="150" />');

    expect($html)->toContain('<picture>');
    expect($html)->toContain('<img');
    expect($html)->toContain('alt="Hero"');
});

it('passes through additional attributes', function () {
    $html = Blade::render('<x-image-toolkit::picture src="images/hero.jpg" alt="Hero" class="w-full" loading="lazy" />');

    expect($html)->toContain('class="w-full"');
    expect($html)->toContain('loading="lazy"');
});

it('renders without errors when src has no directory', function () {
    $html = Blade::render('<x-image-toolkit::picture src="hero.jpg" alt="Hero" />');

    expect($html)->toContain('<picture>');
    expect($html)->toContain('hero.jpg');
});
