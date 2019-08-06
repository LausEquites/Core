<?php


namespace Core;


class Controller
{
    public function serve()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        if (method_exists($this, $method)) {
            return $this->$method();
        } else {
            throw new \Exception("Not implemented - $method");
        }
    }
}