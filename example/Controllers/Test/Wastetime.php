<?php

namespace Controllers\Test;

use Core\Controller\Json;
use Core\Tasks\Job;
use Core\Tasks\Queue;

class Wastetime extends Json {
    public function GET() {
        $job = Job::create('TimeWaster', ['timeToWaste' => 1000]);
        Queue::get()->addJob($job);

        return ['msg' => 'WASTETIME'];
    }
}
