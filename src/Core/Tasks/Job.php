<?php

namespace Core\Tasks;

class Job {
    private $type = '';
    private $params = [];

    /**
     * Create a new job instance
     *
     * @param string $type
     * @param array $params
     * @return Job
     */
    public static function create($type, $parameters = [])
    {
        if (empty($type))
        {
            throw new \Exception('Job type cannot be empty', ['params' => $parameters]);
        }

        $job = new Job();
        $job->type = $type;
        $job->params = $parameters;
        return $job;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getParameters()
    {
        return $this->params;
    }
}
