<?php

namespace Utils;

use Core\Exceptions\External;
use Core\Observability\Log;
use Core\Observability\Trace;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Boot {
    public static function init() {
        self::initLogger();
        self::initTracer();
    }

    public static function close()
    {
        Trace::close();
    }

    private  static function initTracer()
    {
        Trace::init();
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
        $rootSpan = Trace::getRootSpan();
        $rootSpan->addEvent('Handling request');
        $router = \Core\Router::getInstance();
        $router->loadRoutes(__DIR__ . '/../config/structure.xml');
        $router->setNameSpace('Controllers');
        $router->run();

        fastcgi_finish_request();
        $rootSpan->addEvent('Content sent');
    }
}
