<?php

namespace Utils\Jobs;

use Core\Observability\Tracer;

class TimeWaster {
    private $params = [];

    public function __construct($params) {
        $this->params = $params;
    }

    public function run() {
        $timeToWaste = $this->params['timeToWaste'];
        $span = Tracer::startSpan('Wasting time');
        usleep($timeToWaste);
        $span->end();
    }
}
