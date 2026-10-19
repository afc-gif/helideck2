<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiHealthTest extends TestCase
{
    public function test_api_health_endpoint_returns_a_json_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'artsci-survey-api',
            ]);
    }

    public function test_api_health_endpoint_reports_database_unavailability_without_details(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new \RuntimeException('private database failure'));

        $this->getJson('/api/health')
            ->assertServiceUnavailable()
            ->assertExactJson([
                'status' => 'unavailable',
                'service' => 'artsci-survey-api',
            ])
            ->assertDontSee('private database failure');
    }
}
