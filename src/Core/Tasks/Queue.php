<?php

namespace Core\Tasks;

use Core\Observability\Log;
use Core\Observability\Tracer;
use Utils\Jobs\TimeWaster;

class Queue {
    private static $queue;
    private $jobs = [];
    private $jobNamespace;
    /**
     * Get the singleton instance of the queue
     *
     * @return Queue
     */
    public static function get()
    {
        if (!self::$queue)
        {
            self::$queue = new Queue();
        }

        return self::$queue;
    }

    public static function setDefaultJobNamespace($jobNamespace)
    {
        $queue = self::get();
        $queue->jobNamespace = $jobNamespace;
    }

    public function addJob($job)
    {
        $this->jobs[] = $job;
    }

    public function run()
    {
        if ($this->jobs) {
            if (Tracer::isEnabled()) {
                $span = Tracer::startFrameworkSpan('Queue: Processing jobs');
            }
            Log::info('Queue: Processing jobs', ['count' => count($this->jobs)]);

            foreach ($this->jobs as $job) {
                try {
                    $classPath = $this->jobNamespace . '\\' . $job->getType();
                    $class = new $classPath($job->getParameters());
                    $class->run();
                } catch (\Throwable $e) {
                    Log::error('Queue: Uncaught exception', ['exception' => $e]);
                }
            }

            if (Tracer::isEnabled()) {
                $span->end();
            }
        }
    }
}
