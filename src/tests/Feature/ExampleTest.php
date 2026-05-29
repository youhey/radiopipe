<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @internal
 */
class ExampleTest extends TestCase
{
    public function testRootRouteReturnsNotFound(): void
    {
        $response = $this->get('/');

        $response->assertNotFound();
    }
}
