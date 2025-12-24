<?php

namespace Core\Observability\OTEL;

use OpenTelemetry\Context\Propagation\PropagationGetterInterface;

/** HTTPPropagationGetter
 *
 * This class is needed for opentelemetry to be able to extract the trace header.
 * They do provide classes on their own for this, but they do an excess work
 */
class HTTPPropagationGetter implements PropagationGetterInterface
{
    public function keys($carrier): array
    {
        return array_keys($carrier);
    }

    public function get($carrier, $key): ?string
    {
        $header = "HTTP_" . strtoupper($key);

        return $carrier[$header] ?? null;
    }
}
