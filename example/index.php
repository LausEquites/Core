<?php

ini_set('display_errors', 1);

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