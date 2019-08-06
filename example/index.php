<?php

include "../vendor/autoload.php";
include "Controllers/Foo.php";
include "Controllers/Test.php";

$router = Core\Router::getInstance();
$router->loadRoutes('config/structure.xml');
$router->setNameSpace('Controllers');
$router->run();