<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Controller;
use Core\Controller\Json;
use Core\Exceptions\External;
use ReflectionProperty;
use stdClass;
use Tests\Support\UnitTester;

class TestController extends Controller
{
    public function GET()
    {
        return 'get';
    }

    public function GET_PARAMS()
    {
        return 'get with params';
    }
}

class TestMetaController extends Controller
{
    public function META()
    {
        return [
            'methods' => [
                'POST' => [
                    'params' => [
                        'json' => [
                            'required' => ['name' => 'string'],
                            'optional' => ['nickname' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function POST()
    {
        return $this->getParameters();
    }

    public function GET()
    {
        return 'no schema';
    }
}

class TestJsonController extends Json
{
    public function GET()
    {
        return ['ok' => true];
    }

    public function POST()
    {
        return '{"raw":true}';
    }
}

final class ControllerCest
{
    private mixed $previousMethod;

    public function _before(UnitTester $I): void
    {
        $this->previousMethod = $_SERVER['REQUEST_METHOD'] ?? null;
    }

    public function _after(UnitTester $I): void
    {
        if ($this->previousMethod === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $this->previousMethod;
        }
    }

    /**
     * Simulate a decoded JSON request body, since php://input is empty in CLI.
     */
    private function setBody(Controller $controller, object $body): void
    {
        (new ReflectionProperty(Controller::class, 'parameters'))->setValue($controller, $body);
    }

    public function testServeDispatchesToRequestMethod(UnitTester $I): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $I->assertEquals('get', (new TestController())->serve());
    }

    public function testServeDispatchesToParamsVariantWithOwnRouterParameters(UnitTester $I): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $controller = new TestController();
        $controller->setOwnRouterParameters(['id']);

        $I->assertEquals('get with params', $controller->serve());
        $I->assertEquals(['id'], $controller->getOwnRouterParameters());
    }

    public function testServeUnknownMethodThrowsNotImplemented(UnitTester $I): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $I->expectThrowable(new External('Not implemented - DELETE', 501), function () {
            (new TestController())->serve();
        });
    }

    public function testServeWithEmptyBodyGivesEmptyParameters(UnitTester $I): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $controller = new TestController();

        $controller->serve();

        $I->assertEquals(new stdClass(), $controller->getParameters());
        $I->assertEquals('fallback', $controller->getParameter('missing', 'fallback'));
    }

    public function testGetParameter(UnitTester $I): void
    {
        $controller = new TestController();
        $body = new stdClass();
        $body->name = 'Test';
        $body->empty = null;
        $this->setBody($controller, $body);

        $I->assertEquals('Test', $controller->getParameter('name'));
        $I->assertNull($controller->getParameter('empty', 'default'));
        $I->assertNull($controller->getParameter('missing'));
    }

    public function testRouterParameters(UnitTester $I): void
    {
        $controller = new TestController();
        $I->assertNull($controller->getRouterParameters());

        $controller->setRouterParameters(['id' => '7', 'zero' => '0']);

        $I->assertEquals(['id' => '7', 'zero' => '0'], $controller->getRouterParameters());
        $I->assertEquals('7', $controller->getRouterParameter('id'));
        $I->assertEquals('0', $controller->getRouterParameter('zero', 'default'));
        $I->assertEquals('default', $controller->getRouterParameter('missing', 'default'));
        $I->assertNull($controller->getRouterParameter('missing'));
    }

    public function testMetaValidationFiltersValidBody(UnitTester $I): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $controller = new TestMetaController();
        $body = new stdClass();
        $body->name = 'Valid';
        $body->extra = 'dropped';
        $this->setBody($controller, $body);

        $result = $controller->serve();

        $I->assertEquals('Valid', $result->name);
        $I->assertFalse(property_exists($result, 'extra'));
    }

    public function testMetaValidationRejectsInvalidBody(UnitTester $I): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $controller = new TestMetaController();
        $this->setBody($controller, new stdClass());

        try {
            $controller->serve();
            $I->fail('Expected External exception');
        } catch (External $e) {
            $I->assertEquals('Invalid JSON', $e->getMessage());
            $I->assertEquals(400, $e->getCode());
            $I->assertNotEmpty($e->getErrors());
        }
    }

    public function testMetaWithoutSchemaForMethodSkipsValidation(UnitTester $I): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $I->assertEquals('no schema', (new TestMetaController())->serve());
    }

    public function testJsonServeEncodesNonStringResults(UnitTester $I): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $I->assertEquals('{"ok":true}', (new TestJsonController())->serve());
    }

    public function testJsonServeReturnsStringResultsAsIs(UnitTester $I): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $I->assertEquals('{"raw":true}', (new TestJsonController())->serve());
    }

    public function testJsonErrorObject(UnitTester $I): void
    {
        $error = Json::getErrorObject('Invalid token');

        $I->assertInstanceOf(stdClass::class, $error);
        $I->assertEquals('Invalid token', $error->error);
        $I->assertEquals('{"error":"Invalid token"}', json_encode($error));
    }
}
