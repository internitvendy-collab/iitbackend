<?php

namespace Tests\Feature;

use Tests\TestCase;

class BackendStatusRouteTest extends TestCase
{
    public function test_root_route_reports_that_the_backend_is_running(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertExactJson([
                'status' => 'success',
                'message' => 'IIT Backend API is Running',
            ]);
    }
}
