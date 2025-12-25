<?php

// CORS
use Core\Observability\Log;
use Utils\Boot;
//phpinfo(); die();
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: *');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

ini_set('display_errors', 1);
ini_set('html_errors', 0);

include_once __DIR__ . "/../vendor/autoload.php";
include "Controllers/Foo.php";
include "Controllers/Test.php";
include "Controllers/Json.php";
include "Controllers/Openapi.php";
include "Controllers/Houses.php";
include "Controllers/Houses/Floors.php";
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



