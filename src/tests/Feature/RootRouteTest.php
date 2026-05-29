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

    public function testFaviconAssetsExist(): void
    {
        foreach ([
            'favicon.ico',
            'favicon-16x16.png',
            'favicon-32x32.png',
            'apple-touch-icon.png',
            'icon.svg',
            'icon-16.png',
            'icon-32.png',
            'icon-64.png',
            'icon-128.png',
            'icon-256.png',
            'icon-512.png',
        ] as $file) {
            $path = public_path($file);

            self::assertFileExists($path);
            self::assertGreaterThan(0, filesize($path));
        }
    }
}
