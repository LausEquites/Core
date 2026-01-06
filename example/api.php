<?php


use Core\Observability\Log;
use Utils\Boot;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: *');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Should not be in production
if (file_exists(__DIR__ . "/../c3.php")) {
    define('C3_CODECOVERAGE_ERROR_LOG_FILE', '/tmp/c3_error.log');
    include __DIR__ . "/../c3.php";
}

include_once __DIR__ . "/../vendor/autoload.php";
include "Controllers/Test/Foo.php";
include "Controllers/Test/Test.php";
include "Controllers/Test/Json.php";
include "Controllers/Test/Wastetime.php";
include "Controllers/Openapi.php";
include "Controllers/Houses.php";
include "Controllers/Houses/Floors.php";
include "Utils/Jobs/TimeWaster.php";
include "Utils/Boot.php";

putenv("OTEL_EXPORTER_OTLP_ENDPOINT=http://otel:4318");
putenv("OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf");
putenv("OTEL_EXPORTER_OTLP_TIMEOUT=1500");

try {
    Boot::init();
    Boot::run();
}
catch (\Throwable $e) {
    Log::handleUncaughtExceptionJSON($e);
} finally {
    Boot::close();
}



