<?php

namespace Core\Observability;

use Monolog\ErrorHandler;

/**
 * Logger class for handling log messages using PSR-3 compatible logger.
 *
 * This provide a singleton logger based on a PSR-3 compatible logger.
 */
class Log {
    private static $logger;

    /**
     * Sets a PSR-compatible logger instance to be used by the application.
     *
     * @param mixed $logger An instance of a PSR-compliant logger.
     * @return void
     */
    public static function setPSRLogger($logger)
    {
        self::$logger = $logger;
    }

    public static function getLogger()
    {
        return self::$logger;
    }

    public static function emergency($message, array $context = [])
    {
        self::$logger->emergency($message, $context);
    }

    public static function alert($message, array $context = [])
    {
        self::$logger->alert($message, $context);
    }

    public static function critical($message, array $context = [])
    {
        self::$logger->critical($message, $context);
    }

    public static function error($message, array $context = [])
    {
        self::$logger->error($message, $context);
    }

    public static function warning($message, array $context = [])
    {
        self::$logger->warning($message, $context);
    }

    public static function notice($message, array $context = [])
    {
        self::$logger->notice($message, $context);
    }

    public static function info($message, array $context = [])
    {
        self::$logger->info($message, $context);
    }

    public static function debug($message, array $context = [])
    {
        self::$logger->debug($message, $context);
    }

    public static function initMonolog($logger)
    {
        self::setPSRLogger($logger);
    }

    public static function handleUncaughtExceptionJSON(\Throwable $e)
    {
        $logger = Log::getLogger();
        if ($rootSpan = Tracer::getRootSpan()) {
            $rootSpan->recordException($e);
            $rootSpan->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR);
        }

        $return = ['msg' => ''];
        if ($e instanceof \Core\Exceptions\External) {
            $return['msg'] = $e->getMessage();
            $return['errors'] = $e->getErrors();
            $returnCode = $e->getCode();
            $logMessage = 'Uncaught \'external\' exception';
            $logContext = ['exception' => $e, 'code' => $returnCode];
            if ($returnCode < 500 || $returnCode == 501) {
                $logger->notice($logMessage, $logContext);
            } else {
                $logger->error($logMessage, $logContext);
            }
        } else {
            $logger->error('Uncaught exception', ['exception' => $e]);
            $returnCode = 500;
        }

        if ($returnCode >= 500 && $returnCode != 501) {
            $return['msg'] = 'Internal Server Error';
        }

        if (!headers_sent()) {
            http_response_code($returnCode);
            header('Content-Type: application/json');
        }

        echo json_encode($return);
    }
}
