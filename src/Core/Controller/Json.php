<?php

namespace Core\Controller;

use Core\Controller;
use Core\Exceptions\External;

class Json extends Controller
{

    /**
     * @return false|string
     * @throws \Exception
     */
    public function serve()
    {
        header("Content-Type: application/json");
        $out = parent::serve();
        if (!is_string($out)) {
            $out = json_encode($out);
        }

        return $out;
    }

    public static function getErrorObject($error)
    {
        $obj = new \stdClass();
        $obj->error = $error;

        return $obj;
    }
}