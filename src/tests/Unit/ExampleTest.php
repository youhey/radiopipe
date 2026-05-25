<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * @internal
 */
class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function testApplicationNameIsConfigured(): void
    {
        self::assertSame('radiopipe', config('app.name'));
    }
}
