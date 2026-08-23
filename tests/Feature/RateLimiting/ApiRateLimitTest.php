<?php

namespace Tests\Feature\RateLimiting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_usage_stays_below_the_rate_limit(): void
    {
        Sanctum::actingAs(User::factory()->create());

        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/v1/enums')->assertOk();
        }
    }

    public function test_authenticated_endpoints_are_rate_limited(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = null;
        for ($i = 0; $i < 125; $i++) {
            $response = $this->getJson('/api/v1/enums');
        }

        $response->assertStatus(429)->assertJsonPath('success', false);
    }
}
