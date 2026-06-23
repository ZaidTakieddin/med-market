<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationErrorShapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_request_validation_uses_the_nested_error_contract(): void
    {
        $this->postJson('/api/v1/auth/register', [])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => ['title' => 'Validation failed'],
            ])
            ->assertJsonStructure([
                'success',
                'error' => ['title', 'message'],
                'errors',
            ]);
    }
}
