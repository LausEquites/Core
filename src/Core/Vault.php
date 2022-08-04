<?php


namespace Core;


class Vault
{
    static private $instance;
    private $data = [];

    static public function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function get($name)
    {
        return $this->data[$name]?? null;
    }

    public function set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function unset($name)
    {
        unset($this->data[$name]);
    }
    
    public function clear()
    {
        $this->data = [];
    }
}
