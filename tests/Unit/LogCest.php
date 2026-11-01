<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Exceptions\External;
use Core\Observability\Log;
use Core\Observability\Tracer;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use ReflectionProperty;
use Tests\Support\UnitTester;

final class LogCest
{
    private TestHandler $handler;
    private mixed $previousLogger;
    private array $previousTracerItems;

    public function _before(UnitTester $I): void
    {
        $this->previousLogger = Log::getLogger();
        $this->previousTracerItems = $this->tracerItems()->getValue();
        $this->tracerItems()->setValue(null, []);

        $this->handler = new TestHandler();
        Log::setPSRLogger(new Logger('test', [$this->handler]));
    }

    public function _after(UnitTester $I): void
    {
        Log::setPSRLogger($this->previousLogger);
        $this->tracerItems()->setValue(null, $this->previousTracerItems);
    }

    private function tracerItems(): ReflectionProperty
    {
        return new ReflectionProperty(Tracer::class, 'tracerItems');
    }

    /**
     * Run the uncaught exception handler and return its decoded JSON output.
     */
    private function handle(\Throwable $e): array
    {
        ob_start();
        Log::handleUncaughtExceptionJSON($e);

        return json_decode(ob_get_clean(), true);
    }

    public function testSetAndGetLogger(UnitTester $I): void
    {
        $logger = new Logger('other');

        Log::setPSRLogger($logger);
        $I->assertSame($logger, Log::getLogger());

        $monolog = new Logger('monolog');
        Log::initMonolog($monolog);
        $I->assertSame($monolog, Log::getLogger());
    }

    public function testLevelMethodsDelegateToLogger(UnitTester $I): void
    {
        $levels = [
            'emergency' => Level::Emergency,
            'alert' => Level::Alert,
            'critical' => Level::Critical,
            'error' => Level::Error,
            'warning' => Level::Warning,
            'notice' => Level::Notice,
            'info' => Level::Info,
            'debug' => Level::Debug,
        ];

        foreach ($levels as $method => $level) {
            Log::$method("$method message", ['level' => $method]);
        }

        $records = $this->handler->getRecords();
        $I->assertCount(count($levels), $records);
        foreach (array_values($levels) as $i => $level) {
            $method = array_keys($levels)[$i];
            $I->assertSame($level, $records[$i]->level);
            $I->assertEquals("$method message", $records[$i]->message);
            $I->assertEquals(['level' => $method], $records[$i]->context);
        }
    }

    public function testLevelMethodsDefaultToEmptyContext(UnitTester $I): void
    {
        Log::info('no context');

        $I->assertEquals([], $this->handler->getRecords()[0]->context);
    }

    public function testHandleExternalClientErrorExposesMessageAndErrors(UnitTester $I): void
    {
        $e = new External('Invalid JSON', 400);
        $e->setErrors(['name' => 'required']);

        $output = $this->handle($e);

        $I->assertEquals(['msg' => 'Invalid JSON', 'errors' => ['name' => 'required']], $output);
        $I->assertTrue($this->handler->hasNoticeThatContains("Uncaught 'external' exception"));
        $I->assertEquals(400, $this->handler->getRecords()[0]->context['code']);
        $I->assertSame($e, $this->handler->getRecords()[0]->context['exception']);
    }

    public function testHandleExternalNotImplementedIsNoticeWithMessage(UnitTester $I): void
    {
        $output = $this->handle(new External('Not implemented - PATCH', 501));

        $I->assertEquals('Not implemented - PATCH', $output['msg']);
        $I->assertTrue($this->handler->hasNoticeThatContains("Uncaught 'external' exception"));
        $I->assertFalse($this->handler->hasErrorRecords());
    }

    public function testHandleExternalServerErrorHidesMessage(UnitTester $I): void
    {
        $e = new External('Database password is wrong', 503);
        $e->setErrors(['detail' => 'secret']);

        $output = $this->handle($e);

        $I->assertEquals('Internal Server Error', $output['msg']);
        $I->assertTrue($this->handler->hasErrorThatContains("Uncaught 'external' exception"));
        $I->assertEquals(503, $this->handler->getRecords()[0]->context['code']);
    }

    public function testHandleGenericExceptionHidesMessage(UnitTester $I): void
    {
        $e = new \RuntimeException('Something internal');

        $output = $this->handle($e);

        $I->assertEquals(['msg' => 'Internal Server Error'], $output);
        $I->assertTrue($this->handler->hasErrorThatContains('Uncaught exception'));
        $I->assertSame($e, $this->handler->getRecords()[0]->context['exception']);
    }

    public function testHandleRecordsExceptionOnRootSpan(UnitTester $I): void
    {
        $exporter = new InMemoryExporter();
        $tracerProvider = new TracerProvider(new SimpleSpanProcessor($exporter));
        $rootSpan = $tracerProvider->getTracer('test')->spanBuilder('root')->startSpan();
        $this->tracerItems()->setValue(null, ['rootSpan' => $rootSpan]);

        $this->handle(new \RuntimeException('Traced failure'));
        $rootSpan->end();

        $span = $exporter->getSpans()[0];
        $I->assertEquals(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
        $I->assertCount(1, $span->getEvents());
        $I->assertEquals('exception', $span->getEvents()[0]->getName());
        $I->assertEquals('Traced failure', $span->getEvents()[0]->getAttributes()->get('exception.message'));
    }
}
