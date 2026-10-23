<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $connection = $app->make('db')->connection();
        if (! $app->environment('testing') || $connection->getDriverName() !== 'sqlite'
            || $connection->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Refusing automated tests outside the isolated SQLite :memory: database. Clear application config cache first.');
        }

        return $app;
    }
}
