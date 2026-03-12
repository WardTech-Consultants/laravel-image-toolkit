<?php

namespace WardTech\ImageToolkit\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use WardTech\ImageToolkit\ImageToolkitServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ImageToolkitServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
    }
}
