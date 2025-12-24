<?php

namespace Core\Observability;


use Core\Observability\OTEL\HTTPPropagationGetter;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Trace\TracerProviderFactory;

class Trace {

    private static $tracer;
    private static $tracerItems = [];

    public static function init() {
        $tracePropagator = TraceContextPropagator::getInstance();
        $context = $tracePropagator->extract($_SERVER, new HTTPPropagationGetter());

        $factory = new TracerProviderFactory();
        $tracerProvider = $factory->create();

        $tracer = $tracerProvider->getTracer('main');

        $rootSpan = $tracer->spanBuilder('root')
            ->setParent($context)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        self::$tracerItems['rootSpan'] = $rootSpan;
        self::$tracerItems['rootScope'] = $rootSpan->activate();
        self::$tracer = $tracer;
        self::$tracerItems['tracerProvider'] = $tracerProvider;
    }

    public static function close()
    {
        if (self::$tracer) {
            self::$tracerItems['rootSpan']->end();
            self::$tracerItems['rootScope']->detach();
            self::$tracerItems['tracerProvider']->shutdown();

        }
    }

    /**
     * Retrieves the root span from the tracer items.
     *
     * This method accesses the tracer items array and returns the value associated
     * with the 'rootSpan' key, which represents the root span in a tracing operation.
     *
     * @return SpanInterface The root span object or value stored in the tracer items.
     */
    public static function getRootSpan()
    {
        return self::$tracerItems['rootSpan'];
    }
}