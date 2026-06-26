<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Locks the standardized error contract: every API error path returns
 * { success:false, error:{title,message} } with the correct HTTP status,
 * regardless of which exception type Laravel raised (now that controllers
 * defer to the central handler in bootstrap/app.php).
 */
class ErrorContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_nested_401(): void
    {
        $this->getJson('/api/v1/cart')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.title', 'Unauthenticated')
            ->assertJsonStructure(['success', 'error' => ['title', 'message']]);
    }

    public function test_unknown_route_returns_nested_404(): void
    {
        $this->getJson('/api/v1/this-route-does-not-exist')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.title', 'Not found')
            ->assertJsonStructure(['success', 'error' => ['title', 'message']]);
    }

    public function test_missing_model_returns_nested_404_without_leaking_class(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->deleteJson('/api/v1/cart/items/999999')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.title', 'Not found')
            ->assertJsonPath('error.message', 'Resource not found.');

        $this->assertStringNotContainsString('App\\Models', (string) $response->getContent());
    }
}
