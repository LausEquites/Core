<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Observability\Log;
use Core\Observability\Tracer;
use Core\Tasks\Job;
use Core\Tasks\Queue;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use ReflectionProperty;
use Tests\Support\UnitTester;

/**
 * Job classes resolved by the queue via the 'Tests\Unit' job namespace.
 */
class RecordingJob
{
    /** @var array<int, array> Parameters of every run, in run order */
    public static array $runs = [];

    public function __construct(private array $params)
    {
    }

    public function run(): void
    {
        self::$runs[] = $this->params;
    }
}

class FailingJob
{
    public function __construct(array $params)
    {
    }

    public function run(): void
    {
        throw new \RuntimeException('Job failed');
    }
}

final class QueueCest
{
    private TestHandler $logHandler;
    private mixed $previousLogger;

    public function _before(UnitTester $I): void
    {
        $this->setStatic(Queue::class, 'queue', null);
        Queue::setDefaultJobNamespace('Tests\Unit');
        RecordingJob::$runs = [];

        $this->previousLogger = Log::getLogger();
        $this->logHandler = new TestHandler();
        Log::setPSRLogger(new Logger('test', [$this->logHandler]));
    }

    public function _after(UnitTester $I): void
    {
        $this->setStatic(Queue::class, 'queue', null);
        $this->setStatic(Tracer::class, 'enabled', false);
        $this->setStatic(Tracer::class, 'frameworkTracer', null);
        Log::setPSRLogger($this->previousLogger);
    }

    private function setStatic(string $class, string $property, mixed $value): void
    {
        (new ReflectionProperty($class, $property))->setValue(null, $value);
    }

    public function testGetReturnsSingleton(UnitTester $I): void
    {
        $I->assertInstanceOf(Queue::class, Queue::get());
        $I->assertSame(Queue::get(), Queue::get());
    }

    public function testRunWithoutJobsDoesNothing(UnitTester $I): void
    {
        Queue::get()->run();

        $I->assertEmpty($this->logHandler->getRecords());
    }

    public function testRunExecutesJobsInOrderWithParameters(UnitTester $I): void
    {
        $queue = Queue::get();
        $queue->addJob(Job::create('RecordingJob', ['n' => 1]));
        $queue->addJob(Job::create('RecordingJob', ['n' => 2]));
        $queue->addJob(Job::create('RecordingJob'));

        $queue->run();

        $I->assertEquals([['n' => 1], ['n' => 2], []], RecordingJob::$runs);
    }

    public function testRunLogsJobCount(UnitTester $I): void
    {
        $queue = Queue::get();
        $queue->addJob(Job::create('RecordingJob'));
        $queue->addJob(Job::create('RecordingJob'));

        $queue->run();

        $I->assertTrue($this->logHandler->hasInfoThatContains('Queue: Processing jobs'));
        $I->assertEquals(['count' => 2], $this->logHandler->getRecords()[0]->context);
    }

    public function testDefaultJobNamespaceIsUsedToResolveJobs(UnitTester $I): void
    {
        Queue::setDefaultJobNamespace('Some\Other\Namespace');
        Queue::get()->addJob(Job::create('RecordingJob'));

        Queue::get()->run();

        $I->assertEmpty(RecordingJob::$runs);
        $I->assertTrue($this->logHandler->hasErrorThatContains('Queue: Uncaught exception'));
    }

    public function testFailingJobIsLoggedAndOtherJobsStillRun(UnitTester $I): void
    {
        $queue = Queue::get();
        $queue->addJob(Job::create('RecordingJob', ['n' => 1]));
        $queue->addJob(Job::create('FailingJob'));
        $queue->addJob(Job::create('RecordingJob', ['n' => 2]));

        $queue->run();

        $I->assertEquals([['n' => 1], ['n' => 2]], RecordingJob::$runs);

        $errors = array_values(array_filter(
            $this->logHandler->getRecords(),
            fn ($record) => $record->level->getName() === 'ERROR'
        ));
        $I->assertCount(1, $errors);
        $I->assertEquals('Queue: Uncaught exception', $errors[0]->message);
        $I->assertInstanceOf(\RuntimeException::class, $errors[0]->context['exception']);
        $I->assertEquals('Job failed', $errors[0]->context['exception']->getMessage());
    }

    public function testUnknownJobTypeIsLogged(UnitTester $I): void
    {
        Queue::get()->addJob(Job::create('NoSuchJob'));
        Queue::get()->addJob(Job::create('RecordingJob'));

        Queue::get()->run();

        $I->assertCount(1, RecordingJob::$runs);
        $I->assertTrue($this->logHandler->hasErrorThatContains('Queue: Uncaught exception'));
    }

    public function testRunCreatesFrameworkSpanWhenTracingEnabled(UnitTester $I): void
    {
        $exporter = new InMemoryExporter();
        $tracerProvider = new TracerProvider(new SimpleSpanProcessor($exporter));
        $this->setStatic(Tracer::class, 'frameworkTracer', $tracerProvider->getTracer('core'));
        $this->setStatic(Tracer::class, 'enabled', true);

        Queue::get()->addJob(Job::create('RecordingJob'));
        Queue::get()->run();

        $spans = $exporter->getSpans();
        $I->assertCount(1, $spans);
        $I->assertEquals('Queue: Processing jobs', $spans[0]->getName());
        $I->assertCount(1, RecordingJob::$runs);
    }

    public function testNoSpanCreatedWithoutJobs(UnitTester $I): void
    {
        $exporter = new InMemoryExporter();
        $tracerProvider = new TracerProvider(new SimpleSpanProcessor($exporter));
        $this->setStatic(Tracer::class, 'frameworkTracer', $tracerProvider->getTracer('core'));
        $this->setStatic(Tracer::class, 'enabled', true);

        Queue::get()->run();

        $I->assertEmpty($exporter->getSpans());
    }
}
