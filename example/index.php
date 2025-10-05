<?php

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: *');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

ini_set('display_errors', 1);
ini_set('html_errors', 0);

include_once __DIR__ . "/../vendor/autoload.php";
include "Controllers/Foo.php";
include "Controllers/Test.php";
include "Controllers/Json.php";
include "Controllers/Openapi.php";
include "Controllers/Houses.php";
include "Controllers/Houses/Floors.php";

$router = Core\Router::getInstance();
$router->loadRoutes(__DIR__ . '/config/structure.xml');
$router->setNameSpace('Controllers');
$router->run();