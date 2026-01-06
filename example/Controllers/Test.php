<?php

namespace Controllers;

class Test extends \Core\Controller\Json
{
    public function GET() {
        return ['msg' => 'Endpoints for testing'];
    }
}