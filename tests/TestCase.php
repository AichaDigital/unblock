<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\LaravelRay\RayServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * Registra los service providers necesarios para los tests.
     * En este caso, solo necesitamos el RayServiceProvider para debugging.
     *
     * @see https://myray.app/docs/php/laravel/installation
     *
     * @param  Application  $app
     * @return array<int, string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            RayServiceProvider::class,
        ];
    }
}
