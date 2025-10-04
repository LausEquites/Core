<?php

namespace Core\Router;

use Core\Router;

class OpenAPI {
    /** @var Router  */
    private $router;

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

        return [
            'openapi' => '3.0.4',
            'info' => [
                'title' => 'My API',
                'version' => '1.0.0'
            ],
            'paths' => $nodes
        ];
    }

    private function parseNode($node, &$nodes = [], $path = '', $inheritedParams = [])
    {
        $name = $node['name'];
        $path = $path . '/' . $name;

        $data = [
            'get' => [
                'summary' => 'Get ' . $name,
                'responses' => ['200' =>  ['description' => 'Found']]
            ]
        ];
        if ($inheritedParams) {
            $data['get']['parameters'] = $inheritedParams;
        }
        $nodes[$path] = $data;

        if (isset($node['params']) && $node['params']) {
            foreach ($node['params'] as $param) {
                $path .= "$path/{{$param}}";
                $inheritedParams[] = [
                    'name' => $param,
                    'in' => 'path',
                    'required' => true,
                    'schema' => [ 'type' => 'string'],
                ];
            }

            $data = [
                'get' => [
                    'summary' => 'Get ' . $name,
                    'responses' => ['200' =>  ['description' => 'Found']],
                ]
            ];
            if ($inheritedParams) {
                $data['get']['parameters'] = $inheritedParams;
            }
            $nodes[$path] = $data;
        }

        foreach ($node['children'] as $child) {
            $this->parseNode($child, $nodes, $path, $inheritedParams);
        }
    }
}