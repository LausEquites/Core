<?php


namespace Core;



use Controllers\Foo;

class Router
{
    private static $router;
    private $structureXmlPath;
    private $namespace;

    public static function getInstance()
    {
        if (!self::$router) {
            self::$router = new self();
        }

        return self::$router;
    }

    public function loadRoutes($routeXmlPath)
    {
        $this->structureXmlPath = $routeXmlPath;
    }

    public function setNameSpace($namespace)
    {
        $this->namespace = $namespace;
    }

    public function run()
    {
        $xmlstr = file_get_contents($this->structureXmlPath);
        $xml = new \SimpleXMLElement($xmlstr);
        list($uri) = explode('?',$_SERVER['REQUEST_URI']);

        $lastElement = $xml;
        $parents = [];
        $routerParamNames = [];
        $routerParams = [];
        foreach (explode('/', $uri) as $part) {
            if (!$part) {
                continue;
            }
            if ($lastElement->$part) {
                $parents[] = $lastElement;
                $lastElement = $lastElement->$part;
                $routerParamNames = $lastElement->attributes()->params?
                    explode(",", $lastElement->attributes()->params) : [];
            } elseif ($routerParamNames) {
                $currentParam = array_shift($routerParamNames);
                $routerParams[$currentParam] = $part;
            } else {
                throw new \Exception("404 - $part not found in $uri");
            }
        }

        $controllerName = $this->namespace . "\\". ucfirst($lastElement->getName());
        /** @var Controller $controller */
        $controller = new $controllerName;
        $controller->setRouterParameters($routerParams);

        echo $controller->serve();
    }
}
