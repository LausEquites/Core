<?php

namespace Controllers;

use Core\Controller\Json;

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
                                'builtYear' => 'int'
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