<?php

declare(strict_types=1);

namespace Tests\Support\Extension;

use Codeception\Configuration;
use Codeception\Coverage\Subscriber\Printer;
use Codeception\Events;
use Codeception\Exception\ExtensionException;
use Codeception\Extension;

/**
 * Runs the app on PHP's built-in server while the suite runs.
 *
 * When the tests run with code coverage, every request records its own coverage
 * (see tests/Support/Coverage/prepend.php), which is merged into the Codeception
 * report after the suite. This replaces c3.php.
 */
final class BuiltinServer extends Extension
{
    public static array $events = [
        Events::SUITE_BEFORE => 'startServer',
        Events::SUITE_AFTER => 'stopServer',
    ];

    protected array $config = [
        'host' => '127.0.0.1',
        'port' => 8888,
        'router' => 'example/api.php',
        'timeout' => 5,
    ];

    /** @var resource|null */
    private $process = null;

    private ?string $coverageDir = null;

    public function startServer(): void
    {
        $address = $this->config['host'] . ':' . $this->config['port'];
        $router = Configuration::projectDir() . $this->config['router'];
        $command = [PHP_BINARY, '-S', $address, '-t', dirname($router)];
        $env = getenv();

        if (!empty($this->options['coverage'])) {
            $this->coverageDir = Configuration::outputDir() . 'builtin-server-coverage';
            if (!is_dir($this->coverageDir)) {
                mkdir($this->coverageDir, 0777, true);
            }
            array_map('unlink', glob($this->coverageDir . '/*.cov'));

            $env['CODECEPT_COVERAGE_DIR'] = $this->coverageDir;
            array_push($command, '-d', 'auto_prepend_file=' . dirname(__DIR__) . '/Coverage/prepend.php');
        }
        $command[] = $router;

        $log = Configuration::outputDir() . 'builtin-server.log';
        $this->process = proc_open($command, [['pipe', 'r'], ['file', $log, 'a'], ['file', $log, 'a']], $pipes, Configuration::projectDir(), $env);
        if ($this->process === false) {
            throw new ExtensionException($this, "Could not start the built-in server on $address");
        }

        $deadline = microtime(true) + $this->config['timeout'];
        while (!($socket = @fsockopen($this->config['host'], (int)$this->config['port']))) {
            if (!proc_get_status($this->process)['running'] || microtime(true) > $deadline) {
                $this->stopServer();
                throw new ExtensionException($this, "The built-in server did not start on $address, see $log");
            }
            usleep(50_000);
        }
        fclose($socket);
        $this->writeln("Built-in server running on http://$address");
    }

    public function stopServer(): void
    {
        if ($this->process) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }

        if ($this->coverageDir === null || !isset(Printer::$coverage)) {
            return;
        }
        foreach (glob($this->coverageDir . '/*.cov') as $file) {
            $coverage = @unserialize(file_get_contents($file));
            if ($coverage) {
                Printer::$coverage->merge($coverage);
            }
            unlink($file);
        }
    }
}
