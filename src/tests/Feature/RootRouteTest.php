<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * @internal
 */
class RootRouteTest extends TestCase
{
    public function testRootRouteReturnsCachedPlainTextNotFound(): void
    {
        $response = $this->get('/');

        $response->assertNotFound();
        $response->assertSeeText('Not Found');

        $response->assertContent('Not Found');
        self::assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertTrue($response->headers->getCacheControlDirective('public'));
        self::assertSame('3600', $response->headers->getCacheControlDirective('max-age'));
    }
}
