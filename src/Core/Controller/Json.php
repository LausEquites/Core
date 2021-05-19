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
        try {
            $out = parent::serve();
        } catch(External $e) {
            http_response_code($e->getCode());
            $out = self::getErrorObject($e->getMessage());
        } catch(\Exception $e) {
            http_response_code(500);
            $out = self::getErrorObject($e->getMessage());
        }

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