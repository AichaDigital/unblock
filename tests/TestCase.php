<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            \Spatie\LaravelRay\RayServiceProvider::class,
        ];
    }
}
