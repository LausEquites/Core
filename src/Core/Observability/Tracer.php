<?php

namespace Core\Observability;


use Core\Observability\OTEL\HTTPPropagationGetter;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Trace\Span;
use OpenTelemetry\SDK\Trace\TracerProviderFactory;

class Tracer {

    /** @var \OpenTelemetry\SDK\Trace\Tracer|null */
    private static $tracer;
    private static $tracerItems = [];
    private static $enabled = false;

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
        self::$enabled = true;
    }

    public static function close()
    {
        if (self::$tracer) {
            self::$tracerItems['rootSpan']->end();
            self::$tracerItems['rootScope']->detach();
            self::$tracerItems['tracerProvider']->shutdown();

        }
    }

    public static function startSpan($name, array $attributes = [])
    {
        return self::$tracer->spanBuilder($name)
            ->setAttributes($attributes)
            ->startSpan();
    }

    /**
     * Retrieves the root span from the tracer items.
     *
     * This should normally bnot be used outside the core framework.
     *
     * This method accesses the tracer items array and returns the value associated
     * with the 'rootSpan' key, which represents the root span in a tracing operation.
     *
     * @return Span The root span object or value stored in the tracer items.
     */
    public static function getRootSpan()
    {
        return self::$tracerItems['rootSpan'];
    }

    /**
     * Retrieves the OpenTelemetry tracer instance.
     *
     * This method returns the OpenTelemetry tracer instance that is used for tracing
     * operations within the application. It provides access to the tracer for
     * instrumenting and monitoring application behavior.
     *
     * @return \OpenTelemetry\SDK\Trace\Tracer|null The OpenTelemetry tracer instance.
     */
    public static function getOtelTracer()
    {
       return self::$tracer;
    }

    public static function isEnabled()
    {
        return self::$enabled;
    }
}