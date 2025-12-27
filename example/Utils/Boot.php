<?php

namespace Utils;

use Core\Observability\Log;
use Core\Observability\Tracer;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\SDK\Logs\LoggerProviderFactory;

class Boot {
    private static $otelLogProvider;
    public static function init() {
        self::initTracer();
        self::initLogger();
    }

    public static function close()
    {
        $span = Tracer::startSpan('Locking doors');
        usleep(5000);
        $span->end();

        Tracer::close();
        self::$otelLogProvider->shutdown();
    }

    private  static function initTracer()
    {
        Tracer::init();
    }

    private static function initLogger()
    {
        $logger = new Logger('default');
        $logger->pushHandler(new StreamHandler('php://stdout'));
        self::initOtelLogger($logger);

        Log::setPSRLogger($logger);
        self::initExceptionHandler();
        Log::info('Booting application');
    }

    private static function initOtelLogger($logger)
    {
        $logFactory = new LoggerProviderFactory();
        $loggerProvider = $logFactory->create();
        self::$otelLogProvider = $loggerProvider;
        $handler = new \OpenTelemetry\Contrib\Logs\Monolog\Handler($loggerProvider, \Psr\Log\LogLevel::INFO,);
        $logger->pushHandler($handler);
        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            $span = Span::getCurrent();
            $ctx  = $span->getContext();

            if (!$ctx->isValid()) {
                return $record;
            }

            $context = $record->context;
            $context['trace_id'] = $ctx->getTraceId();
            $context['span_id']  = $ctx->getSpanId();

            return $record->with(context: $context);
        });
    }

    private static function initExceptionHandler()
    {
        set_exception_handler([Log::class, 'handleUncaughtExceptionJSON']);
    }

    public static function run() {
        $rootSpan = Tracer::getRootSpan();
        $rootSpan->addEvent('Handling request');
        $router = \Core\Router::getInstance();
        $router->loadRoutes(__DIR__ . '/../config/structure.xml');
        $router->setNameSpace('Controllers');
        $router->run();

        fastcgi_finish_request();
        $rootSpan->addEvent('Content sent');
    }
}
