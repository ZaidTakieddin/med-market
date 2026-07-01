<?php

namespace Tests\Feature\Companies;

use App\Constants\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['media.disk' => 'public']);
        Storage::fake('public');
    }

    public function test_admin_can_upload_a_company_logo(): void
    {
        $company = Company::factory()->supplier()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->postJson("/api/v1/companies/{$company->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertNotNull($response->json('data.logoUrl'));

        $company->refresh();
        $this->assertNotNull($company->logo_path);
        Storage::disk($company->logo_disk)->assertExists($company->logo_path);
    }

    public function test_replacing_a_logo_removes_the_previous_file(): void
    {
        $company = Company::factory()->supplier()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson("/api/v1/companies/{$company->id}/logo", [
            'logo' => UploadedFile::fake()->image('first.png'),
        ])->assertOk();

        $oldPath = $company->fresh()->logo_path;

        $this->postJson("/api/v1/companies/{$company->id}/logo", [
            'logo' => UploadedFile::fake()->image('second.png'),
        ])->assertOk();

        $newPath = $company->fresh()->logo_path;

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_user_from_another_company_cannot_upload_a_logo(): void
    {
        $company = Company::factory()->supplier()->create();
        // A supplier belonging to a DIFFERENT company.
        Sanctum::actingAs(User::factory()->supplier()->create());

        $this->postJson("/api/v1/companies/{$company->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertStatus(403);
    }

    public function test_owning_supplier_can_upload_and_remove_own_company_logo(): void
    {
        $company = Company::factory()->supplier()->create();
        $user = User::factory()->create(['role' => UserRole::SUPPLIER, 'company_id' => $company->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/companies/{$company->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertOk();

        $path = $company->fresh()->logo_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->deleteJson("/api/v1/companies/{$company->id}/logo")->assertOk();

        $this->assertNull($company->fresh()->logo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_non_image_logo_is_rejected(): void
    {
        $company = Company::factory()->supplier()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson("/api/v1/companies/{$company->id}/logo", [
            'logo' => UploadedFile::fake()->create('logo.pdf', 50, 'application/pdf'),
        ])->assertStatus(422);
    }

    public function test_admin_can_remove_a_company_logo(): void
    {
        $company = Company::factory()->supplier()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson("/api/v1/companies/{$company->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertOk();

        $path = $company->fresh()->logo_path;

        $this->deleteJson("/api/v1/companies/{$company->id}/logo")
            ->assertOk()
            ->assertJsonPath('data.logoUrl', null);

        $company->refresh();
        $this->assertNull($company->logo_path);
        $this->assertNull($company->logo_disk);
        Storage::disk('public')->assertMissing($path);
    }
}
