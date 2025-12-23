<?php

namespace Utils;

use Core\Exceptions\External;
use Core\Observability\Log;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Boot {
    public static function init() {
        self::initLogger();
    }

    private static function initLogger()
    {
        $logger = new Logger('default');
        $logger->pushHandler(new StreamHandler('php://stdout'));
        Log::setPSRLogger($logger);
        self::initExceptionHandler();
        Log::info('Booting application');
    }

    private static function initExceptionHandler()
    {
        set_exception_handler([Log::class, 'handleUncaughtExceptionJSON']);
    }

    public static function run() {
        $router = \Core\Router::getInstance();
        $router->loadRoutes(__DIR__ . '/../config/structure.xml');
        $router->setNameSpace('Controllers');
        $router->run();
    }
}
