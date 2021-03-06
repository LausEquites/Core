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
        $parentControllerNames = [];
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
                $routerParamsByController[$lastElement->getName()][$currentParam] = $part;
            } else {
                throw new \Exception("404 - $part not found in $uri");
            }
        }

        $controllerName = $this->namespace . "\\". ucfirst($lastElement->getName());
        /** @var Controller $controller */
        $controller = new $controllerName;
        $controller->setRouterParameters($routerParams);
        if (isset($routerParamsByController[$lastElement->getName()])) {
            $controller->setOwnRouterParameters($routerParamsByController[$lastElement->getName()]);
        }

        foreach ($parents as $parentXML) {
            $className = $this->namespace . "\\". ucfirst($parentXML->getName());
            if (class_exists($className, true) && method_exists($className,'preServe')) {
                $class = new $className;
                $class->preServe();
            }
        }

        if (method_exists($controller,'preServe')) {
            $controller->preServe();
        }
        echo $controller->serve();
    }
}
