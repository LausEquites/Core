<?php

namespace Controllers;

use Core\Controller\Json;
use Core\Router;

class Openapi extends Json {

    public static function META()
    {
        return [
            'methods' => [
                'GET' => [
                    'title' => 'Get OpenAPI JSON',
                    'description' => 'Get OpenAPI JSON endpoint',
                    'tags' => ['Utils'],
                ]
            ]
        ];
    }

    function GET()
    {
        $openApi = new \Core\Router\OpenAPI(Router::getInstance());
        $openApi->setTitle('Core API example');
        $openApi->setVersion('0.0.1');

        return $openApi->getJson();
    }
}