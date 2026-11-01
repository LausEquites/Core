<?php

/**
 * Loaded through `auto_prepend_file` by the built-in server started from
 * Tests\Support\Extension\BuiltinServer. Records the code coverage of a single
 * request and writes it to CODECEPT_COVERAGE_DIR, where the extension merges it
 * into the Codeception report.
 */

declare(strict_types=1);

use Codeception\Configuration;
use Codeception\Coverage\Filter;
use Codeception\Coverage\PhpCodeCoverageFactory;

(static function (): void {
    $coverageDir = getenv('CODECEPT_COVERAGE_DIR');
    if (!$coverageDir) {
        return;
    }

    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';

    try {
        Configuration::config($root . '/codeception.yml');
        $coverage = PhpCodeCoverageFactory::build();
        (new Filter($coverage))
            ->whiteList(Configuration::config())
            ->blackList(Configuration::config());
    } catch (\Throwable $e) {
        error_log('Code coverage disabled: ' . $e->getMessage());
        return;
    }

    $coverage->start($_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI']);

    // Registered from within a shutdown function so it runs after the app's own shutdown functions
    register_shutdown_function(static function () use ($coverage, $coverageDir): void {
        register_shutdown_function(static function () use ($coverage, $coverageDir): void {
            $coverage->stop();
            file_put_contents($coverageDir . '/' . uniqid('', true) . '.cov', serialize($coverage));
        });
    });
})();
