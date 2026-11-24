<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.connections.mysql.database') !== 'vmas_test'
            || $app['config']->get('database.default') !== 'mysql'
            || $app['config']->get('database.connections.mysql.username') !== 'vmas_test') {
            throw new \RuntimeException('Tests require the isolated vmas_test database and vmas_test user.');
        }

        return $app;
    }
}
