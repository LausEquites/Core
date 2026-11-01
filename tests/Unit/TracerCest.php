<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Observability\Tracer;
use OpenTelemetry\SDK\Trace\Tracer as SdkTracer;
use ReflectionClass;
use Tests\Support\UnitTester;

final class TracerCest
{
    private array $previousStatics = [];
    private string|false $previousExporter;

    public function _before(UnitTester $I): void
    {
        foreach ((new ReflectionClass(Tracer::class))->getStaticProperties() as $name => $value) {
            $this->previousStatics[$name] = $value;
        }
        $this->resetStatics();

        // Initialize a real tracer provider without exporting spans anywhere
        $this->previousExporter = getenv('OTEL_TRACES_EXPORTER');
        putenv('OTEL_TRACES_EXPORTER=none');
    }

    public function _after(UnitTester $I): void
    {
        if (Tracer::getOtelTracer()) {
            Tracer::close();
        }

        putenv($this->previousExporter === false ? 'OTEL_TRACES_EXPORTER' : "OTEL_TRACES_EXPORTER={$this->previousExporter}");
        foreach ($this->previousStatics as $name => $value) {
            (new ReflectionClass(Tracer::class))->setStaticPropertyValue($name, $value);
        }
    }

    private function resetStatics(): void
    {
        $class = new ReflectionClass(Tracer::class);
        $class->setStaticPropertyValue('tracer', null);
        $class->setStaticPropertyValue('frameworkTracer', null);
        $class->setStaticPropertyValue('tracerItems', []);
        $class->setStaticPropertyValue('enabled', false);
    }

    public function testGetOtelTracerIsNullBeforeInit(UnitTester $I): void
    {
        $I->assertNull(Tracer::getOtelTracer());
        $I->assertFalse(Tracer::isEnabled());
    }

    public function testGetOtelTracerReturnsInitializedTracer(UnitTester $I): void
    {
        Tracer::init('my-app');

        $tracer = Tracer::getOtelTracer();

        $I->assertInstanceOf(SdkTracer::class, $tracer);
        $I->assertEquals('my-app', $tracer->getInstrumentationScope()->getName());
        $I->assertSame($tracer, Tracer::getOtelTracer());
        $I->assertTrue(Tracer::isEnabled());
    }

    public function testGetOtelTracerIsTheApplicationTracer(UnitTester $I): void
    {
        Tracer::init();

        $I->assertEquals('main', Tracer::getOtelTracer()->getInstrumentationScope()->getName());
        $I->assertEquals('main', Tracer::startSpan('app span')->getInstrumentationScope()->getName());
        $I->assertEquals('core', Tracer::startFrameworkSpan('framework span')->getInstrumentationScope()->getName());
        $I->assertEquals('core', Tracer::getRootSpan()->getInstrumentationScope()->getName());
    }
}
