<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LegacyApiCompatibilityTest extends TestCase
{
    public function test_no_legacy_php_routes_are_registered(): void
    {
        $legacyRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_ends_with($route->uri(), '.php'));

        $this->assertCount(0, $legacyRoutes);
    }

    public function test_every_api_route_lives_under_v1(): void
    {
        $nonV1 = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => str_starts_with($uri, 'api/'))
            ->reject(fn (string $uri) => str_starts_with($uri, 'api/v1'))
            ->values();

        $this->assertCount(0, $nonV1, 'Unexpected non-v1 API routes: ' . $nonV1->implode(', '));
    }

    public function test_v1_login_route_is_registered_as_post(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($route) => $route->uri() === 'api/v1/auth/login');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
    }

    public function test_registered_routes_do_not_dispatch_to_legacy_controller(): void
    {
        $legacyRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->getActionName(), 'LegacyApiController'));

        $this->assertCount(0, $legacyRoutes);
    }
}
