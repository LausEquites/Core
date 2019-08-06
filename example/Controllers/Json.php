<?php


namespace Controllers;


use Core\Controller;

class Json extends Controller\Json
{
    public function GET()
    {
        $out = new \stdClass();
        $out->foo = "bar";

        return $out;
    }
}