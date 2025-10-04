<?php

namespace Controllers;

use Core\Controller\Json;
use Core\Router;

class Openapi extends Json {

    function GET()
    {
        $openApi = new \Core\Router\OpenAPI(Router::getInstance());
        return $openApi->getJson();
    }
}