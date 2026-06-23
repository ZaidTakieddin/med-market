<?php

namespace Tests\Unit\Auth;

use App\Http\Requests\Auth\RegisterRequest;
use Tests\TestCase;

/**
 * Guards the security contract for public self-registration: the request must
 * never accept a client-supplied role or company_id (privilege escalation), and
 * must require a company name (the server creates a new buyer company).
 * See codebase rules §17 lesson #1.
 */
class RegisterRequestTest extends TestCase
{
    public function test_registration_does_not_accept_role_or_company_id(): void
    {
        $rules = (new RegisterRequest)->rules();

        $this->assertArrayNotHasKey('role', $rules, 'Public registration must not accept a client-supplied role.');
        $this->assertArrayNotHasKey('company_id', $rules, 'Public registration must not accept a client-supplied company_id.');
    }

    public function test_registration_requires_a_company_name(): void
    {
        $rules = (new RegisterRequest)->rules();

        $this->assertArrayHasKey('company_name', $rules);
        $this->assertContains('required', $rules['company_name']);
    }
}
