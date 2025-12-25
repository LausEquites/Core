<?php

namespace Core;

use Core\Exceptions\External;
use Core\Observability\Log;
use Core\Observability\Trace;

/**
 * Core\Router
 *
 * Minimal XML-driven router that maps request URIs to controller classes and invokes them.
 *
 * Responsibilities:
 * - Load a hierarchical XML structure (structure.xml) that mirrors the URL path.
 * - Walk the request URI (segments split by '/') against XML nodes.
 * - Resolve controller class names from node names and configured namespace.
 * - Support per-node attributes:
 *   - child-ns: append to namespace for child elements during traversal.
 *   - params: comma-separated path parameter names consumed by subsequent segments.
 * - Instantiate controllers found along the traversed path and call their optional preServe().
 * - Inject router parameters into each instantiated controller via setRouterParameters().
 * - Mark per-controller owned parameters via setOwnRouterParameters() for _PARAMS dispatch.
 * - Finally, call serve() on the last controller and echo its result.
 *
 * structure.xml format:
 * - The XML is hierarchical; each element name corresponds to a path segment.
 * - The root element maps to "/". Child elements extend the path by their tag name.
 * - Attributes on an element modify how its descendants are resolved:
 *   - child-ns: additional namespace segment to prepend for all child elements under this node.
 *   - params: comma-separated list of path parameter names; upcoming URI segments are captured in order.
 *
 * Example:
 * <root>
 *   <api>
 *     <login/>
 *     <users child-ns="Users" params="userId">
 *       <settings/>
 *     </users>
 *   </api>
 * </root>
 *
 * With Router::setNameSpace('Controllers') this resolves as:
 * - /                   -> Controllers\Root (if defined)
 * - /api                -> Controllers\Api
 * - /api/login          -> Controllers\Login
 * - /api/users          -> Controllers\Users
 * - /api/users/settings -> Controllers\Users\Settings
 *
 * Error handling:
 * - If a URI segment doesn't match a node and no pending param is expected, throws Exception("404 - ...").
 * - Missing controller classes are allowed; only existing classes are instantiated.
 */
class Router
{
    /**
     * @var Router Singleton instance
     */
    private static $router;

    /**
     * @var string Path to the XML structure file used for routing.
     */
    private $structureXmlPath;

    /**
     * @var string Root PHP namespace for controller classes (e.g., 'Controllers').
     */
    private $namespace;

    /**
     * @var string[] Node names and parameter tokens (e.g., ":userId") that form the matched path.
     */
    private $path = [];

    /**
     * Get the singleton Router instance.
     *
     * @return Router
     */
    public static function getInstance()
    {
        if (!self::$router) {
            self::$router = new self();
        }

        return self::$router;
    }

    /**
     * Register the XML file containing the route structure.
     *
     * @param string $routeXmlPath Path to structure.xml
     * @return void
     */
    public function loadRoutes($routeXmlPath)
    {
        $this->structureXmlPath = $routeXmlPath;
    }

    /**
     * Set the root namespace used to resolve controller classes.
     * Example: 'Controllers' -> Controllers\Api, Controllers\Users, ...
     *
     * @param string $namespace Root namespace for controllers
     * @return void
     */
    public function setNameSpace($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * Execute routing for the current HTTP request.
     *
     * Process:
     * - Load and parse the XML structure.
     * - Split REQUEST_URI and traverse nodes accordingly.
     * - Instantiate controller classes found along the path.
     * - Collect and distribute router parameters to controllers.
     * - Call preServe() on each controller if defined, in traversal order.
     * - Call serve() on the final controller and echo its output.
     *
     * @return void
     * @throws \Exception On 404 resolution failures.
     */
    public function run()
    {
        $xmlstr = file_get_contents($this->structureXmlPath);
        $xml = new \SimpleXMLElement($xmlstr);
        [$uri] = explode('?', $_SERVER['REQUEST_URI']);

        $lastElement = $xml;
        $controllers = [];
        $routerParamNames = [];
        $routerParams = [];
        $routerParamsByController = [];
        $namespace = $this->namespace;

        foreach (explode('/', $uri) as $part) {
            if (!$part) {
                continue;
            }
            if ($lastElement->$part) {
                $this->path[] = $lastElement->$part->getName();
                $fullClassName = $namespace . "\\" . ucfirst($lastElement->$part->getName());
                if (class_exists($fullClassName, true)) {
                    $controllers[] = new $fullClassName;
                }

                $lastElement = $lastElement->$part;
                $attributes = $lastElement->attributes();
                $routerParamNames = $attributes->params ?
                    explode(',', $lastElement->attributes()->params) : [];
                if ($attributes->{"child-ns"}) {
                    $namespace .= "\\" . $attributes->{"child-ns"};
                }
            } elseif ($routerParamNames) {
                // Consume a parameter segment
                $currentParam = array_shift($routerParamNames);
                $this->path[] = ":" . $currentParam;
                $routerParams[$currentParam] = $part;
                $routerParamsByController[$fullClassName][$currentParam] = $part;
            } else {
                Log::notice("Router: $part not found in $uri");
                throw new External("Not found", 404);
            }
        }

        // Inject parameters and run preServe hooks
        $preServe = [];
        foreach ($controllers as $controller) {
            $controller->setRouterParameters($routerParams);
            $ownParams = $routerParamsByController[$controller::class] ?? [];
            $controller->setOwnRouterParameters($ownParams);
            if (method_exists($controller, 'preServe')) {
                $preServe[] = $controller;
            }
        }
        $tracer = Trace::getTracer();
        if ($preServe) {
            if ($tracer) { $preServeSpan = Trace::startSpan('router.preServe');}
            foreach ($preServe as $controller) {
                $controller->preServe();
            }
            if ($tracer) { $preServeSpan->end();}
        }

        // Serve the final controller in the chain
        if ($tracer) { $serveSpan = Trace::startSpan('router.serve');}
        echo $controller->serve();
        if ($tracer) { $serveSpan->end();}
    }

    /**
     * Return the matched path as an array of nodes and param tokens.
     *
     * Example: ["api", ":userId", "settings"]. Useful for debugging/logging.
     *
     * @return string[]
     */
    public function getPath()
    {
        return $this->path;
    }

    public function getNodesNested()
    {
        $xmlstr = file_get_contents($this->structureXmlPath);
        $xml = new \SimpleXMLElement($xmlstr);

        return $this->parseNode($xml, $this->namespace);
    }

    /**
     * @param $node
     * @return array
     */
    private function parseNode($node, $namespace = '')
    {
        $attributes = $node->attributes();
        $params = $attributes->params ? explode(',', $attributes->params) : [];
        $childNs = $attributes->{"child-ns"}?? null;
        $children = [];
        $newNamespace = $namespace;
        if ($childNs) {
            $newNamespace .= "\\$childNs";
        }
        foreach ($node->children() as $child) {
            $children[] = $this->parseNode($child, $newNamespace);
        }

        $node = [
            'name' => $node->getName(),
            'params' => $params,
            'class' => $namespace . "\\" . ucfirst($node->getName()),
            'childNs' => $childNs,
            'children' => $children,
        ];
        if ($params) {
            $node['params'] = $params;
        }
        if ($childNs) {
            $node['childNs'] = $childNs;
        }

        return $node;
    }
}
