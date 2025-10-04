<?php

namespace Controllers\Houses;

use Core\Controller\Json;

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
            ]
        ];
    }

    public function GET()
    {
        return 'Floors';
    }

    public function GET_PARAMS()
    {
        return 'Get Floor';
    }
}