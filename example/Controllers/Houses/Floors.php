<?php

namespace Controllers\Houses;

use Core\Controller\Json;
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
        usleep(10000);
        $span->end();

        return 'Floors';
    }

    public function GET_PARAMS()
    {
        return 'Get Floor';
    }
}