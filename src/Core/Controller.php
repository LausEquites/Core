<?php


namespace Core;


use Core\Exceptions\External;
use Phdantic\Phdantic;

/**
 * Core\Controller
 *
 * Lightweight base controller used by the router to dispatch HTTP requests.
 *
 * Responsibilities:
 * - Parse JSON request body once per request and expose it via getParameters()/getParameter().
 * - Expose router parameters (from structure.xml) via getRouterParameters()/getRouterParameter().
 * - Dispatch to a method named after the HTTP verb (GET, POST, PATCH, DELETE, ...).
 *   If the controller owns route params, the method name will have the suffix "_PARAMS".
 * - When a verb method is not implemented, throw Core\Exceptions\External with status 501.
 *
 * Typical subclassing:
 * - Define methods like GET(), POST(), etc. Optionally also define GET_PARAMS(), POST_PARAMS() if
 *   the route defines params and this controller declares them as its own via setOwnRouterParameters().
 * - Optionally implement preServe() in your controller(s) in the route chain; the router will call
 *   these before serve() (GET(), POST(), etc.). These are called hierarchically related to the path with the first
 *   controller with a preServe() method being called first. This can be used to stop propagation if a condition is not met in a paraent node.
 *   A good example is a controller that checks for authentication.
 */
class Controller
{
    private $parameters;
    private $routerParameters;
    private $ownRouterParameters;

    /**
     * Dispatch the HTTP request to the matching controller method.
     *
     * Method resolution:
     * - Uses $_SERVER['REQUEST_METHOD'] (e.g., GET, POST, PATCH, DELETE) as the method name.
     * - If this controller has own router params (see setOwnRouterParameters()),
     *   appends "_PARAMS" to the method name (e.g., GET_PARAMS).
     *
     * Error handling:
     * - If the resolved method does not exist, throws Core\\Exceptions\\External with status 501.
     *
     * @return mixed The return value of the resolved method. Subclasses like Controller\\Json may encode it.
     * @throws External If the HTTP method is not implemented.
     */
    public function serve()
    {
        $this->loadParameters();

        $method = $_SERVER['REQUEST_METHOD'];
        if ($this->ownRouterParameters) {
            $method .= "_PARAMS";
        }
        if (method_exists($this, $method)) {
            if (method_exists($this, 'META')) {
                $this->validataParams($method);
            }

            return $this->$method();
        } else {
            throw new External("Not implemented - $method", 501);
        }
    }

    private function validataParams($method)
    {
        $meta = $this->META();
        $scheme = $meta['methods'][$method]['params']?? [];
        if (isset($scheme['json'])) {
            if (!Phdantic::validateObject($this->getParameters(), $scheme['json'])) {
                $e = new External('Invalid JSON', 400);
                $e->setErrors(Phdantic::getLastErrors());
                throw $e;
            }
            $this->parameters = Phdantic::filterObject($this->getParameters(), $scheme['json']);
        }
    }

    /**
     * Lazily loads and caches the JSON request body into $this->parameters as stdClass.
     *
     * Reading from php://input; if the body is empty, uses an empty JSON object ({}).
     */
    private function loadParameters()
    {
        if ($this->parameters === null) {
            $json = file_get_contents('php://input')?? '{}';
            $this->parameters = (object) json_decode($json);
        }
    }

    /**
     * Get the decoded JSON request body as an object (stdClass).
     *
     * @return object|null The request body object, or null if not present.
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Get a single value from the JSON request body.
     *
     * @param string $name The property name to read from the request body object.
     * @param mixed $default A default value to return when the property is absent.
     * @return mixed The value if present; otherwise $default.
     */
    public function getParameter($name, $default = null)
    {
        if (property_exists($this->parameters,$name)) {
            return $this->parameters->{$name};
        }

        return $default;
    }

    /**
     * Get all router parameters for the current route.
     *
     * @return array|null Associative array of router parameters or null if none were set.
     */
    public function getRouterParameters()
    {
        return $this->routerParameters;
    }

    /**
     * Get a single router parameter by name.
     *
     * @param string $name Parameter name
     * @param mixed $default Default value if not present. If null, returns null when absent.
     * @return mixed|null The value or the default/null.
     */
    public function getRouterParameter($name, $default = null)
    {
        if (isset($this->routerParameters[$name])) {
            return $this->routerParameters[$name];
        } elseif ($default !== null) {
            return $default;
        } else {
            return null;
        }
    }

    /**
     * Set all router parameters for the current request.
     * Typically called by the router.
     *
     * @param array $params Associative array of router parameters.
     * @return void
     */
    public function setRouterParameters($params)
    {
        $this->routerParameters = $params;
    }

    /**
     * Get the subset of router parameters owned by this controller.
     *
     * The set of "own" parameter names must be provided via setOwnRouterParameters().
     * This helper returns an associative array of just those parameters for convenience
     * when implementing *_PARAMS methods.
     *
     * @return array Associative array of this controller's own router parameters (name => value).
     */
    public function getOwnRouterParameters()
    {
        return $this->ownRouterParameters;
    }

    /**
     * Declare which router parameters are considered this controller's own.
     *
     * When this set is non-empty, serve() will dispatch to *_PARAMS variants
     * (e.g., GET_PARAMS) if present. Use together with getOwnRouterParameters().
     *
     * @param string[] $parameters List of parameter names owned by this controller.
     * @return void
     */
    public function setOwnRouterParameters($parameters)
    {
        $this->ownRouterParameters = $parameters;
    }
}
