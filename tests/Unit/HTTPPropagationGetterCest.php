<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Observability\OTEL\HTTPPropagationGetter;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\Span;
use Tests\Support\UnitTester;

final class HTTPPropagationGetterCest
{
    private const TRACEPARENT = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

    public function testGetReadsHeaderFromServerStyleCarrier(UnitTester $I): void
    {
        $carrier = ['HTTP_TRACEPARENT' => self::TRACEPARENT, 'HTTP_TRACESTATE' => 'vendor=value'];
        $getter = new HTTPPropagationGetter();

        $I->assertEquals(self::TRACEPARENT, $getter->get($carrier, 'traceparent'));
        $I->assertEquals(self::TRACEPARENT, $getter->get($carrier, 'TraceParent'));
        $I->assertEquals('vendor=value', $getter->get($carrier, 'tracestate'));
    }

    public function testGetMissingHeaderReturnsNull(UnitTester $I): void
    {
        $getter = new HTTPPropagationGetter();

        $I->assertNull($getter->get([], 'traceparent'));
        $I->assertNull($getter->get(['TRACEPARENT' => self::TRACEPARENT], 'traceparent'));
    }

    public function testKeysReturnsCarrierKeys(UnitTester $I): void
    {
        $carrier = ['HTTP_TRACEPARENT' => self::TRACEPARENT, 'REQUEST_METHOD' => 'GET'];

        $I->assertEquals(['HTTP_TRACEPARENT', 'REQUEST_METHOD'], (new HTTPPropagationGetter())->keys($carrier));
        $I->assertEquals([], (new HTTPPropagationGetter())->keys([]));
    }

    public function testTraceContextPropagatorExtractsParentFromServerVars(UnitTester $I): void
    {
        $carrier = ['HTTP_TRACEPARENT' => self::TRACEPARENT, 'REQUEST_METHOD' => 'GET'];

        $context = TraceContextPropagator::getInstance()->extract($carrier, new HTTPPropagationGetter());
        $spanContext = Span::fromContext($context)->getContext();

        $I->assertTrue($spanContext->isValid());
        $I->assertTrue($spanContext->isRemote());
        $I->assertEquals('4bf92f3577b34da6a3ce929d0e0e4736', $spanContext->getTraceId());
        $I->assertEquals('00f067aa0ba902b7', $spanContext->getSpanId());
        $I->assertTrue($spanContext->isSampled());
    }

    public function testTraceContextPropagatorWithoutHeaderGivesInvalidContext(UnitTester $I): void
    {
        $context = TraceContextPropagator::getInstance()->extract(['REQUEST_METHOD' => 'GET'], new HTTPPropagationGetter());

        $I->assertFalse(Span::fromContext($context)->getContext()->isValid());
    }
}
