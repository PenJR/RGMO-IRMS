<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Boot tests only when they are isolated from the application's database.
     */
    public function createApplication(): Application
    {
        $application = parent::createApplication();
        $connection = $application['config']->get('database.default');
        $database = $application['config']->get("database.connections.{$connection}.database");

        if ($database !== ':memory:') {
            throw new RuntimeException(
                'Tests must use the in-memory SQLite database; refusing to modify the application database.'
            );
        }

        return $application;
    }

    /**
     * Set up.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
    }
}
