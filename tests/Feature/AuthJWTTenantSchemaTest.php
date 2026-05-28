<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthJWT;
use stdClass;
use Tests\TestCase;

class AuthJWTTenantSchemaTest extends TestCase
{
    public function test_resolve_tenant_schema_from_payload(): void
    {
        config([
            'tenancy.default_schema' => 'public',
            'tenancy.fallback_to_default_schema' => true,
            'tenancy.allowed_schemas' => ['client_a', 'client_b', 'public'],
        ]);

        $middleware = new AuthJWT();
        $method = new \ReflectionMethod($middleware, 'resolveTenantSchema');
        $method->setAccessible(true);

        $payload = new stdClass();
        $payload->tenant_schema = 'client_a';

        $resolved = $method->invoke($middleware, $payload);

        $this->assertSame('client_a', $resolved);
    }

    public function test_reject_invalid_tenant_schema_from_payload(): void
    {
        config([
            'tenancy.default_schema' => 'public',
            'tenancy.fallback_to_default_schema' => false,
            'tenancy.allowed_schemas' => ['client_a', 'client_b'],
        ]);

        $middleware = new AuthJWT();
        $method = new \ReflectionMethod($middleware, 'resolveTenantSchema');
        $method->setAccessible(true);

        $payload = new stdClass();
        $payload->tenant_schema = 'client-a';

        $resolved = $method->invoke($middleware, $payload);

        $this->assertNull($resolved);
    }
}
