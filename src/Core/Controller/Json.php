<?php

namespace Core\Controller;

use Core\Controller;

/**
 * Core\\Controller\\Json
 *
 * Convenience controller base for JSON APIs. Extend this class when you want:
 * - Content-Type: application/json automatically set.
 * - Non-string return values from your action methods to be JSON-encoded.
 *
 * Usage:
 * class Users extends Core\\Controller\\Json { public function GET() { return ['ok' => true]; } }
 *
 * Notes:
 * - If your action method already returns a string, it will be returned as-is (no extra encoding).
 * - json_encode() is used for non-strings with default options. Customize by returning a string you encoded yourself.
 * - Pairs with Core\\Exceptions\\External for error reporting; you may also use getErrorObject() to standardize error shapes.
 */
class Json extends Controller
{
    /**
     * Dispatch and ensure JSON output.
     *
     * Behavior:
     * - Calls parent::serve() to invoke the HTTP verb method on your controller.
     * - Sets the Content-Type header to application/json.
     * - If the returned value is not a string, json_encode() is applied.
     *
     * @return false|string JSON string or a string returned by the action; false on failure from json_encode (rare).
     * @throws \Exception Propagates exceptions from the underlying action or routing logic.
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

    /**
     * Helper to produce a standard error-shaped object.
     *
     * Example:
     *   return self::getErrorObject('Invalid token');
     *   // -> {"error":"Invalid token"}
     *
     * @param mixed $error Any value that represents the error (string or structured data).
     * @return \stdClass Object with a single property 'error'.
     */
    public static function getErrorObject($error)
    {
        $obj = new \stdClass();
        $obj->error = $error;

        return $obj;
    }
}