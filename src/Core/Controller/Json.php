<?php

namespace Core\Controller;

use Core\Controller;

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
}