<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Controller\Json;
use Core\Exceptions\External;
use Core\Observability\Log;
use Core\Router;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\Support\UnitTester;

/**
 * Controllers for tests/Support/Data/structure.xml. The 'catalog' node intentionally has no controller.
 */
class Shop extends Json
{
    public function GET()
    {
        return ['controller' => 'shop'];
    }
}

class Products extends Json
{
    public function GET()
    {
        return ['controller' => 'products'];
    }

    public function GET_PARAMS()
    {
        return ['controller' => 'products', 'params' => $this->getOwnRouterParameters()];
    }
}

class Reviews extends Json
{
    public function GET()
    {
        return ['controller' => 'reviews', 'productId' => $this->getRouterParameter('productId')];
    }
}

final class RouterCest
{
    private const STRUCTURE = __DIR__ . '/../Support/Data/structure.xml';

    private array $previousServer;
    private mixed $previousLogger;

    public function _before(UnitTester $I): void
    {
        $this->previousServer = $_SERVER;
        $this->previousLogger = Log::getLogger();
        Log::setPSRLogger(new Logger('test', [new TestHandler()]));
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function _after(UnitTester $I): void
    {
        $_SERVER = $this->previousServer;
        Log::setPSRLogger($this->previousLogger);
    }

    private function createRouter(): Router
    {
        $router = new Router();
        $router->loadRoutes(self::STRUCTURE);
        $router->setNameSpace('Tests\Unit');

        return $router;
    }

    /**
     * Route the given URI and return the decoded JSON output.
     */
    private function route(Router $router, string $uri): mixed
    {
        $_SERVER['REQUEST_URI'] = $uri;
        ob_start();
        try {
            $router->run();
        } finally {
            $output = ob_get_clean();
        }

        return json_decode($output, true);
    }

    public function testGetPathIsEmptyBeforeRun(UnitTester $I): void
    {
        $I->assertEquals([], $this->createRouter()->getPath());
    }

    public function testGetPathForStaticRoute(UnitTester $I): void
    {
        $router = $this->createRouter();

        $output = $this->route($router, '/shop/');

        $I->assertEquals(['controller' => 'shop'], $output);
        $I->assertEquals(['shop'], $router->getPath());
    }

    public function testGetPathReplacesParameterValuesWithNames(UnitTester $I): void
    {
        $router = $this->createRouter();

        $output = $this->route($router, '/shop/products/42/reviews?sort=desc');

        $I->assertEquals(['controller' => 'reviews', 'productId' => '42'], $output);
        $I->assertEquals(['shop', 'products', ':productId', 'reviews'], $router->getPath());
    }

    public function testGetPathWithMultipleParameters(UnitTester $I): void
    {
        $router = $this->createRouter();

        $output = $this->route($router, '/shop/products/42/blue');

        $I->assertEquals(['controller' => 'products', 'params' => ['productId' => '42', 'variantId' => 'blue']], $output);
        $I->assertEquals(['shop', 'products', ':productId', ':variantId'], $router->getPath());
    }

    public function testGetPathIncludesNodesWithoutController(UnitTester $I): void
    {
        $router = $this->createRouter();

        // 'catalog' has no controller, so the last instantiated controller (Shop) serves the request
        $output = $this->route($router, '/shop/catalog');

        $I->assertEquals(['controller' => 'shop'], $output);
        $I->assertEquals(['shop', 'catalog'], $router->getPath());
    }

    public function testGetPathKeepsMatchedPrefixOnNotFound(UnitTester $I): void
    {
        $router = $this->createRouter();

        $I->expectThrowable(new External('Not found', 404), function () use ($router) {
            $this->route($router, '/shop/products/42/1/unknown');
        });
        $I->assertEquals(['shop', 'products', ':productId', ':variantId'], $router->getPath());
    }

    public function testGetInstanceReturnsSingleton(UnitTester $I): void
    {
        $router = Router::getInstance();

        $I->assertSame($router, Router::getInstance());
        $I->assertNotSame($router, $this->createRouter());
    }
}
