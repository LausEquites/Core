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
        $controllers = [];
        $routerParamNames = [];
        $routerParams = [];
        $namespace = $this->namespace;
        foreach (explode('/', $uri) as $part) {
            if (!$part) {
                continue;
            }
            if ($lastElement->$part) {
                $parents[] = $lastElement;
                $fullClassName = $namespace . "\\" . ucfirst($lastElement->$part->getName());
                if (class_exists($fullClassName, true)) {
                    $controllers[] = new $fullClassName;
                }

                $lastElement = $lastElement->$part;
                $attributes = $lastElement->attributes();
                $routerParamNames = $attributes->params?
                    explode(",", $lastElement->attributes()->params) : [];
                if ($attributes->{"child-ns"}) {
                    $namespace .= "\\" . $attributes->{"child-ns"};
                }
            } elseif ($routerParamNames) {
                // Handle parameters
                $currentParam = array_shift($routerParamNames);
                $routerParams[$currentParam] = $part;
                $routerParamsByController[$fullClassName][$currentParam] = $part;
            } else {
                throw new \Exception("404 - $part not found in $uri");
            }
        }

        foreach ($controllers as $controller) {
            $controller->setRouterParameters($routerParams);
            $ownParams = $routerParamsByController[$controller::class]?? [];
            $controller->setOwnRouterParameters($ownParams);
            if (method_exists($controller,'preServe')) {
                $controller->preServe();
            }
        }

        echo $controller->serve();
    }
}