<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Con la config cacheada (php artisan config:cache) phpunit.xml no aplica
     * y RefreshDatabase vaciaría la base MySQL real. Se corta antes de que
     * corra ningún trait de base de datos.
     */
    protected function setUpTraits()
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException(
                'Tests bloqueados: no apuntan a SQLite en memoria (¿config cacheada?). Ejecuta "php artisan config:clear" y vuelve a intentar.'
            );
        }

        return parent::setUpTraits();
    }
}
