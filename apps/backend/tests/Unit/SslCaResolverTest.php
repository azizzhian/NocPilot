<?php

namespace Tests\Unit;

use App\Support\SslCaResolver;
use Tests\TestCase;

class SslCaResolverTest extends TestCase
{
    public function test_resolves_bundled_or_laragon_ca_bundle(): void
    {
        $path = SslCaResolver::resolve();

        $this->assertNotNull($path);
        $this->assertFileExists($path);
    }
}
