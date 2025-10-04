# Core PHP framework

A lightweight, modular framework focused on keeping things simple while providing the essentials for building APIs and apps.

This repository contains the Core runtime (routing, controllers, ActiveRecord, config, etc.) and a minimal example app showing how to wire it up.


## General

### File structure (recommended for apps using Core)
- app/ — application code
- app/backend/ — backend services (API, domain, DB access)
- app/frontend/ — frontend assets/app
- app/public/ — public web root (web server document root)


## Backend

The backend framework is LausEquites/Core (this repo). Your application code lives in your own repository; it consumes Core as a dependency and follows the structure and conventions below.


## Project file structure (backend)

- config/ — configuration files (e.g., routing structure.xml)
- db/ — Phinx database migrations
- NAMESPACE/ — your application namespace root
  - NAMESPACE/Controllers/ — controllers
  - NAMESPACE/Models/ — models

See the example app under the example/ directory for a minimal working setup.


## Controllers

### Controller basics
Controllers are linked to routes declared in an XML file (config/structure.xml). The XML is hierarchical with a root element mapping to the path /. The router resolves controllers by walking this tree.

- In your bootstrap, define the root class namespace for controllers. In the example app:
  - Router::loadRoutes('config/structure.xml')
  - Router::setNameSpace('Controllers')
  - See example/index.php
- Each XML element name maps to a class under the configured namespace. Child elements can use a different namespace via attributes.

Supported XML attributes:
- child-ns — the class namespace for child elements (can be nested)
- params — comma-separated parameters that become part of the path (available to controller methods)

Example XML:
```
<root>
    <api>
        <login/>
        <users child-ns="Users" params="userId">
            <settings/>
        </users>
    </api>
</root>
```
Path to class resolution:
- /                    -> NAMESPACE\Controllers\Root
- /api                 -> NAMESPACE\Controllers\Api
- /api/login           -> NAMESPACE\Controllers\Login
- /api/users           -> NAMESPACE\Controllers\Users
- /api/users/settings  -> NAMESPACE\Controllers\Users\Settings

### Controller classes
If your controller extends Core\Controller\Json, you can return any data; the Json controller will set the Content-Type and JSON-encode non-string responses.

HTTP method dispatch:
- The method used to call the endpoint (GET, POST, PATCH, etc.) determines the method invoked on the resolved controller class.
- If the route defines params, the method name will have _PARAMS appended.

Example: GET /api/users resolves to NAMESPACE\Controllers\Users::GET

Lifecycle hooks:
- preServe(): called on all classes in the request path before the main method. Use this to enforce auth, short-circuit requests, or populate properties for later use.

Errors:
- Throw Core\Exceptions\External for API errors. The exception code is used as the HTTP status code. Messages for 5xx (>= 500) are not returned to the client.

### Controller methods
- Use $this->getParameter(name) or $this->getParameters() to access path params.
- Use $this->getRouterParameter(name) for router parameters.




## Example
A minimal example is provided under example/:

- example/config/structure.xml — route structure
- example/index.php — bootstraps the router and sets the namespace
- example/Controllers — sample controllers including a JSON controller example

Quick start (from example/):
```php
$router = Core\Router::getInstance();
$router->loadRoutes('config/structure.xml');
$router->setNameSpace('Controllers');
$router->run();
```


## Core\\Router reference

The Core\\Router is a minimal XML-driven router that maps request URIs to controller classes and dispatches them.

What it does:
- Loads a hierarchical XML (structure.xml) mirroring the URL path.
- Splits REQUEST_URI into segments and walks the XML accordingly.
- Resolves controller class names from XML element names under a configured root namespace.
- Supports attributes on XML elements:
  - child-ns: append this namespace segment for all child elements under the current node.
  - params: comma-separated parameter names. Subsequent URI segments will be consumed into these names in order.
- Instantiates controllers found along the traversed path (if the class exists) and calls their optional preServe() before dispatch.
- Injects router parameters into each instantiated controller via setRouterParameters(array $params) and marks per-controller owned params via setOwnRouterParameters(array $names). This enables GET_PARAMS/POST_PARAMS, etc., on controllers that own params.
- Calls serve() on the final controller and echoes its return value. If you extend Core\\Controller\\Json, the output will be JSON with appropriate headers.

404 behavior:
- If a URI segment does not match a node and no pending param is defined for that position, the router throws an Exception with message starting with "404 -".

Path inspection:
- getPath(): returns an array of matched node names and parameter tokens (e.g., ["api", ":userId", "settings"]). Useful for logging/debugging.

Wiring:
- loadRoutes(string $path): path to your structure.xml.
- setNameSpace(string $namespace): root controller namespace, e.g., "Controllers".
- run(): performs the routing and dispatch for the current request.


## Core\\Controller reference

The Core\\Controller class is the lightweight base used by the router to invoke your controller methods.

Key responsibilities:
- Parse the JSON request body once and expose it via getParameters() and getParameter(name, default).
- Expose router parameters (from config/structure.xml) via getRouterParameters() and getRouterParameter(name, default).
- Dispatch to a method named after the HTTP verb (GET, POST, PATCH, DELETE, ...). If the controller declares that it owns route params, the method name will include the suffix _PARAMS.
- Throw Core\\Exceptions\\External with status 501 when a verb method is not implemented.

Request body handling:
- The request body is read from php://input and decoded as JSON into an object (stdClass).
- getParameters(): returns the whole object (or null if absent).
- getParameter($name, $default = null): returns a single property or the provided default.

Router parameters:
- getRouterParameters(): returns all router params as an associative array.
- getRouterParameter($name, $default = null): returns a single router param value or the default/null.
- setRouterParameters(array $params): used by the router to inject params for the current request.

Owning parameters and _PARAMS dispatch:
- setOwnRouterParameters(string[] $names): declare which router parameters are considered this controller’s own.
- getOwnRouterParameters(): returns only the subset of router params owned by this controller.
- When own params are set, serve() will resolve to methods like GET_PARAMS, POST_PARAMS, etc., if they exist.

Dispatch semantics:
- serve(): determines the target method from $_SERVER['REQUEST_METHOD'] and the _PARAMS suffix (if own params are set), then invokes it. If the method does not exist, an External exception is thrown with code 501.

Error handling:
- Throw Core\\Exceptions\\External for client/server errors. Its code is used as the HTTP status. For 5xx errors the message is not exposed to clients.

Tip: If you extend Core\\Controller\\Json, non-string return values from your action methods will be JSON-encoded automatically and the Content-Type: application/json header will be set.


## Core\\Controller\\Json reference

Core\\Controller\\Json is a convenience base class for building JSON APIs.

What it does:
- Sets the HTTP header Content-Type: application/json for you.
- If your action returns a non-string (array, object, scalar), the result is JSON-encoded automatically.
- If your action returns a string, it is returned as-is (no extra encoding), giving you full control when needed.

Usage:
```php
use Core\\Controller;

class Users extends Controller\\Json
{
    public function GET()
    {
        // Will be encoded to {"ok":true,"time":1699999999}
        return ['ok' => true, 'time' => time()];
    }

    public function POST()
    {
        // You can also return a pre-encoded string if you need custom options
        return json_encode(['ok' => true], JSON_PRETTY_PRINT);
    }
}
```

Error helper:
- Json::getErrorObject($error): returns an stdClass with a single property error that you can use for consistent error payloads.
  Example: return self::getErrorObject('Invalid token'); // -> {"error":"Invalid token"}

How it works:
- Json::serve() calls parent::serve() (Core\\Controller::serve()), then sets the JSON header and encodes non-string outputs.
- Combine with Core\\Exceptions\\External to control HTTP error codes from your actions.


## Core\\ActiveRecord reference

Core\\ActiveRecord is a lightweight base for simple table-backed models. Subclass it and define:
- protected static $_table: the database table name.
- protected static $_typeMap: optional map of field => type to control IO conversions.

Supported $_typeMap types:
- dt or datetime: values are Carbon instances in UTC in PHP; stored as 'Y-m-d H:i:s'.
- json or json-object: values are stdClass in PHP; JSON-encoded on write.
- json-assoc: values are associative arrays; JSON-encoded on write.
- bit: values are bound as integers (0/1) when writing (useful for tinyint/bit).
- default/null: no special conversion.

Loading records:
- protected static getById($id): returns a single instance or false when not found.
- protected static getByIdMulti(array $ids): returns an array of instances (possibly empty).
- protected static getBySql(string $sql, array $params = []): returns an array of instances.
- protected static getBySqlSingle(string $sql, array $params = []): first instance or false.

Hydration/mapping:
- public static createFromArray($data): creates an instance and assigns only properties that exist on the subclass. Applies $_typeMap conversions when populating fields (Carbon for dt/datetime; json_decode for json/json-object/json-assoc).

Change tracking and persistence:
- protected function set(string $field, $value): assigns the value and marks the field as modified.
- protected function save():
  - If no fields are modified, returns true immediately.
  - If id is set, performs UPDATE on $_table with only modified fields.
  - If id is not set, performs INSERT with only modified fields and sets id from lastInsertId().
- Parameter binding honors $_typeMap via bindParams():
  - dt: converted to UTC 'Y-m-d H:i:s'.
  - bit: bound as integer (PDO::PARAM_INT).
  - json/json-object/json-assoc: json_encode before binding (throws External on failure).
  - Booleans are normalized to 0/1 automatically.

Notes:
- All times are treated as UTC. When saving Carbon instances, timezone is normalized to UTC.
- Validation, uniqueness, relations, and complex querying are intentionally out of scope for this minimal base; implement these in your subclass or service layer as needed.


## Core\Config reference

Core\Config provides a lazy-loading accessor for your application configuration.

What it does:
- On first access, loads APP_ROOT . '/backend/config/config.php'.
- Caches the loaded config for the lifetime of the request.
- Supports configs returned as either an associative array or an stdClass.

Expected bootstrap:
- Define APP_ROOT in your app bootstrap so it points to your application root.

Config file format (example):
```php
<?php
// app/backend/config/config.php
return [
    'db' => [
        'dsn' => 'mysql:host=localhost;dbname=app',
        'user' => 'app',
        'pass' => 'secret',
    ],
    'env' => 'dev',
];
```

Or as an object:
```php
<?php
$config = new stdClass();
$config->env = 'prod';
$config->featureFlag = true;
return $config;
```

API:
- Config::getAll(): array|object — returns the full config structure as loaded.
- Config::getKey(string $key): mixed — returns a single value by key; throws Exception if the key is missing.

Usage:
```php
use Core\Config;

$cfg = Config::getAll();
$dsn = Config::getKey('db')['dsn']; // when using array format
$env = Config::getKey('env');
```

## Core\\DB reference

Core\\DB provides a simple PDO factory and per-request singleton used by Core components (e.g., ActiveRecord).

What it does:
- DB::get(): returns a shared PDO instance (created on first call and reused).
- DB::getFromConfig(): constructs a new PDO using configuration loaded via Core\\Config.

Configuration:
- The config (Config::getAll()) can be an array or object with a top-level key/property "db".
- Under "db", either supply a full DSN or individual parts:
  - dsn: full PDO DSN string (e.g., "mysql:host=localhost;dbname=app;charset=utf8mb4").
  - user: database username.
  - pass: database password.
  - If dsn is omitted, provide:
    - host: database host.
    - db: database name.
    - port: optional port number.
    - charset: optional charset (default: utf8mb4).
  - options: optional array of PDO options. These are merged over Core defaults
    (Core sets PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION).

Examples:
Array-style config
```php
return [
  'db' => [
    // Option A: full DSN
    'dsn' => 'mysql:host=localhost;dbname=app;charset=utf8mb4',
    'user' => 'app',
    'pass' => 'secret',
    'options' => [\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC],
  ],
];
```

Object-style config
```php
$config = new stdClass();
$config->db = (object) [
  // Option B: host/db parts
  'host' => 'localhost',
  'db' => 'app',
  'user' => 'app',
  'pass' => 'secret',
  'port' => 3306,
  'charset' => 'utf8mb4',
];
return $config;
```

Usage:
```php
use Core\\DB;

$pdo = DB::get();
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => 1]);
$row = $stmt->fetch();
```


## Core\\Vault reference

Core\\Vault is a tiny in-memory key-value store for the duration of a single PHP request. It helps components share ephemeral data without global variables.

What it does:
- Exposes a per-request singleton via Vault::getInstance().
- Stores arbitrary values by string key.
- Allows getting, unsetting a single key, or clearing all keys.

Notes:
- Not a cache across requests or processes. All values are lost when the request ends.
- Use an external cache (Redis/Memcached) or a database if you need persistence.

API:
- Vault::getInstance(): Vault — returns the singleton instance for the current request.
- $vault->set(string $name, mixed $value): void — store a value.
- $vault->get(string $name): mixed|null — retrieve a value or null if not set.
- $vault->unset(string $name): void — remove a key.
- $vault->clear(): void — remove all keys.

Example:
```php
use Core\\Vault;

$vault = Vault::getInstance();
$vault->set('userId', 123);
$vault->set('traceId', bin2hex(random_bytes(8)));

// Later in the same request
$userId = $vault->get('userId');        // 123
$vault->unset('traceId');               // remove single key

// Optionally clear everything at end of request
$vault->clear();
```
