<?php

namespace poldixd\MatomoAIBotTracking\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use poldixd\MatomoAIBotTracking\MatomoAIBotTrackingServiceProvider;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            MatomoAIBotTrackingServiceProvider::class,
        ];
    }
}
