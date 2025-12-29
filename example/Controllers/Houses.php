<?php

namespace Controllers;

use Core\Controller\Json;
use Core\Observability\Tracer;

class Houses extends Json
{
    public static function META()
    {
        return [
            'methods' => [
                'GET' => [
                    'title' => 'List houses',
                    'description' => 'List all houses',
                    'tags' => ['House', 'MCP'],
                ],
                'POST' => [
                    'tags' => ['House'],
                    'params' => [
                        'json' => [
                            'required' => [
                                'name' => 'string',
                                'street' => 'string',
                                'streetNumber' => 'int',
                            ],
                            'optional' => [
                                'builtYear' => 'int',
                                'locked' => 'bool',
                            ]
                        ],
                    ],
                ],
                'GET_PARAMS' => [
                    'tags' => ['House'],
                ],
            ]
        ];
    }

    public function preServe ()
    {
        $span = Tracer::startSpan('Checking locks');
        usleep(2000);
        $span->end();
    }

    public function GET()
    {
        return 'List Houses';
    }

    public function POST()
    {
        return 'POST House';
    }

    public function GET_PARAMS()
    {
        return 'Get House';
    }
}