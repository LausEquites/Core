<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Tasks\Job;
use Tests\Support\UnitTester;

final class JobCest
{
    public function testCreateWithParameters(UnitTester $I): void
    {
        $job = Job::create('SendMail', ['to' => 'test@example.com']);

        $I->assertInstanceOf(Job::class, $job);
        $I->assertEquals('SendMail', $job->getType());
        $I->assertEquals(['to' => 'test@example.com'], $job->getParameters());
    }

    public function testCreateWithoutParametersDefaultsToEmptyArray(UnitTester $I): void
    {
        $job = Job::create('SendMail');

        $I->assertEquals([], $job->getParameters());
    }

    public function testCreateReturnsNewInstanceEachCall(UnitTester $I): void
    {
        $I->assertNotSame(Job::create('SendMail'), Job::create('SendMail'));
    }

    public function testCreateWithEmptyTypeThrows(UnitTester $I): void
    {
        foreach (['', null] as $type) {
            $I->expectThrowable(new \Exception('Job type cannot be empty'), function () use ($type) {
                Job::create($type, ['a' => 1]);
            });
        }
    }
}
