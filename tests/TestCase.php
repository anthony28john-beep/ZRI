<?php

namespace Tests;

use App\Services\FullNames;
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
    }
}
