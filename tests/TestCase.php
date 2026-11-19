<?php

namespace Tests;

use App\Services\FullNames;
use App\Services\StudentNotifications;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    protected function fullNames(): FullNames
    {
        $names = Mockery::mock(FullNames::class);
        $names->shouldReceive('forProfile')->andReturnUsing(fn ($uid, $profile, $fallback = 'Student') => FullNames::local($profile, $fallback));

        return $names;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $notifications = Mockery::mock(StudentNotifications::class);
        $notifications->shouldReceive('forStudent')->andReturn(['count' => 0, 'items' => [], 'unavailable' => []])->byDefault();
        $this->app->instance(StudentNotifications::class, $notifications);
    }
}
