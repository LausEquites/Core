<?php

namespace Controllers;

use Core\Controller\Json;
use Core\Observability\Tracer;
use stdClass;

class Houses extends Json
{
    public static function META()
    {
        return [
            'methods' => [
                'GET' => [
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
        $name = $this->getParameter('name', null);
        $streetNumber = $this->getParameter('streetNumber', null);
        $builtYear = $this->getParameter('builtYear', null);
        $locked = $this->getParameter('locked', null);
        $parameters = $this->getParameters();

        $out = new stdClass();
        $out->name = $name;
        $out->street = $parameters->street;
        $out->streetNumber = $streetNumber;
        $out->builtYear = $builtYear;
        $out->locked = $locked;


        return $out;
    }

    public function GET_PARAMS()
    {
        $routerParams = $this->getOwnRouterParameters();
        $city = $this->getRouterParameter('city', null);
        $floorId = $routerParams['houseId']?? null;
        $out = new \stdClass();
        $out->id = $floorId;
        $out->city = $city;

        return $out;
    }
}