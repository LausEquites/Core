<?php

namespace Core\Router;

use Core\Router;

/**
 * Class OpenAPI
 *
 * Generates an OpenAPI specification in JSON format by parsing route definitions
 * from a provided Router instance.
 */
class OpenAPI {
    /** @var Router  */
    private $router;
    private $title = 'API';
    private $version = '0.0.1';

    /**
     * @param $router Router
     */
    public function __construct($router) {
        $this->router = $router;
    }

    public function getJson()
    {
        $nodeTree = $this->router->getNodesNested();
        $nodes = [];
        $this->parseNode($nodeTree,$nodes);

        $offset = strlen($nodeTree['name']) + 2;
        $filteredNodes = [];
        foreach ($nodes as $path => $node) {
            $filteredNodes["/" . substr($path, $offset)] = $node;
        }

        return [
            'openapi' => '3.0.4',
            'info' => [
                'title' => $this->title,
                'version' => $this->version,
            ],
            'paths' => $filteredNodes,
        ];
    }

    private function parseNode($node, &$nodes = [], $path = '', $inheritedParams = [])
    {
        $name = $node['name'];
        $path = $path . '/' . $name;

        $openApiPath = $this->generatePathsFromNode($node, $inheritedParams, false);
        if ($openApiPath) {
            $nodes[$path] = $openApiPath;
        }


        if (isset($node['params']) && $node['params']) {
            foreach ($node['params'] as $param) {
                $path .= "/{{$param}}";
                $inheritedParams[] = [
                    'name' => $param,
                    'in' => 'path',
                    'required' => true,
                    'schema' => [ 'type' => 'string'],
                ];
            }

            $nodes[$path] = $this->generatePathsFromNode($node, $inheritedParams, true);
        }

        foreach ($node['children'] as $child) {
            $this->parseNode($child, $nodes, $path, $inheritedParams);
        }
    }

    private function generatePathsFromNode($node, $inheritedParams = [], $withParams = false)
    {
        if (!class_exists($node['class'])) {
            return [];
        }

        if (!$withParams) {
            $searchMethods = ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'];
        } else {
            $searchMethods = ['GET_PARAMS', 'POST_PARAMS', 'PATCH_PARAMS', 'PUT_PARAMS', 'DELETE_PARAMS'];
        }

        $classMethods = get_class_methods($node['class']);
        $methods = array_intersect($classMethods, $searchMethods);

        $paths = [];
        foreach ($methods as $method) {
            $pathMethod = $method;
            if ($withParams) {
                $pathMethod = substr($method, 0, -7);
            }
            $paths[strtolower($pathMethod)] = $this->generatePath($node, $method, $inheritedParams);
        }

        return $paths;
    }

    private function generatePath($node, $method, $inheritedParams = [])
    {
        $classMethods = get_class_methods($node['class']);
        $summary = '';
        $description = '';
        $tags = [];

        if (in_array('META', $classMethods)) {
            $meta = $node['class']::META();
            if (isset($meta['methods'][$method]['title'])) {
                $summary = $meta['methods'][$method]['title'];
            }
            if (isset($meta['methods'][$method]['description'])) {
                $description = $meta['methods'][$method]['description'];
            }
            if (isset($meta['methods'][$method]['tags'])) {
                $tags = $meta['methods'][$method]['tags'];
            }
        }

        if (!$summary) {
            $summary = $this->generateDefaultPathSummary($node, $method);
        }

        $path = [
                'summary' => $summary,
                'responses' => ['200' =>  ['description' => 'Found']],
        ];
        if ($inheritedParams) {
            $path['parameters'] = $inheritedParams;
        }
        if ($description) {
            $path['description'] = $description;
        }
        if ($tags) {
            $path['tags'] = $tags;
        }

        return $path;
    }

    private function generateDefaultPathSummary($node, $method)
    {
        $classMethods = get_class_methods($node['class']);
        $action = $method;

        $withParams = false;
        $name = $node['name'];
        if (substr($method, -7) == '_PARAMS') {
            $action = substr($method, 0, -7);
            $name = substr($node['name'],0, -1);
            $withParams = true;
        }

        if (str_starts_with($method, 'GET')
            && !$withParams
            && in_array('GET_PARAMS', $classMethods)
        ) {
            $action = 'List';
        }
        elseif (str_starts_with($method, 'POST')) {
            $name = substr($node['name'],0, -1);
        }

        $action = ucfirst(strtolower($action));

        return "$action $name";
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setVersion($version)
    {
        $this->version = $version;
    }
}
