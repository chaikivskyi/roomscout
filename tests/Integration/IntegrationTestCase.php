<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class IntegrationTestCase extends KernelTestCase
{
    private static ?string $previousIntegrationHttp = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $previous = $_ENV['INTEGRATION_HTTP'] ?? null;
        self::$previousIntegrationHttp = \is_string($previous) ? $previous : null;

        $_ENV['INTEGRATION_HTTP'] = '1';
        $_SERVER['INTEGRATION_HTTP'] = '1';
    }

    public static function tearDownAfterClass(): void
    {
        $restored = self::$previousIntegrationHttp ?? '0';
        $_ENV['INTEGRATION_HTTP'] = $restored;
        $_SERVER['INTEGRATION_HTTP'] = $restored;

        parent::tearDownAfterClass();
    }

    protected static function requireApiKey(string $name): void
    {
        $key = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        if (!\is_string($key) || '' === $key) {
            self::markTestSkipped(sprintf('%s is not set in .env.test.local; skipping the live API probe.', $name));
        }
    }
}
