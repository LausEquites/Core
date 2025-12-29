<?php

namespace Controllers\Houses;

use Core\Controller\Json;
use Core\Observability\Log;
use Core\Observability\Tracer;

class Floors extends Json
{
    public static function META()
    {
        return [
            'methods' => [
                'GET' => [
                    'title' => 'List ALL floors',
                    'description' => 'List all the floors in the building',
                    'tags' => ['House', 'MCP'],
                ],
                'GET_PARAMS' => [
                    'title' => 'List ONE floor',
                    'description' => 'List one  floor in the building',
                    'tags' => ['House'],
                ],
            ],
        ];
    }

    public function preServe ()
    {
        $span = Tracer::startSpan('Checking floor stuff');
        usleep(5000);
        $span->end();
    }

    public function GET()
    {
        $span = Tracer::startSpan('Getting floors');
        $floorId = $this->getRouterParameter('houseId', null);
        $out = new \stdClass();
        $out->houseId = $floorId;
        usleep(10000);
        Log::info('Floor map found');
        $span->end();



        return $out;
    }

    public function GET_PARAMS()
    {
        return 'Get Floor';
    }
}