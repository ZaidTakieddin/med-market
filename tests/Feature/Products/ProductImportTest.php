<?php

namespace Tests\Feature\Products;

use App\Constants\UserRole;
use App\Jobs\ImportProductsJob;
use App\Models\Company;
use App\Models\ProductImport;
use App\Models\ProductImportError;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    private function csvUpload(): UploadedFile
    {
        return UploadedFile::fake()->create('products.csv', 20, 'text/csv');
    }

    public function test_supplier_can_start_an_import_and_job_is_dispatched(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = User::factory()->supplier()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/products/import', ['file' => $this->csvUpload()])
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('product_imports', [
            'supplier_company_id' => $user->company_id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        Queue::assertPushed(ImportProductsJob::class);
    }

    public function test_buyer_cannot_start_an_import(): void
    {
        Storage::fake('local');
        Queue::fake();

        Sanctum::actingAs(User::factory()->buyer()->create());

        $this->postJson('/api/v1/products/import', ['file' => $this->csvUpload()])
            ->assertStatus(403);

        Queue::assertNotPushed(ImportProductsJob::class);
    }

    public function test_admin_must_provide_supplier_company_id(): void
    {
        Storage::fake('local');
        Queue::fake();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/products/import', ['file' => $this->csvUpload()])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_missing_file_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->supplier()->create());

        $this->postJson('/api/v1/products/import', [])
            ->assertStatus(422);
    }

    public function test_sample_file_can_be_downloaded(): void
    {
        Excel::fake();

        Sanctum::actingAs(User::factory()->supplier()->create());

        $this->get('/api/v1/products/import-sample')->assertOk();

        Excel::assertDownloaded('products_import_sample.xlsx');
    }

    public function test_show_returns_status_and_paginated_errors_for_own_company(): void
    {
        $company = Company::factory()->supplier()->create();
        $user = User::factory()->create(['role' => UserRole::SUPPLIER, 'company_id' => $company->id]);

        $import = ProductImport::factory()->completed()->create([
            'supplier_company_id' => $company->id,
            'created_count' => 2,
            'failed_count' => 1,
        ]);
        ProductImportError::factory()->create([
            'product_import_id' => $import->id,
            'row_number' => 3,
            'sku' => 'BAD-1',
            'message' => 'Unknown category.',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/products/imports/{$import->id}")
            ->assertOk()
            ->assertJsonPath('data.import.id', $import->id)
            ->assertJsonPath('data.import.createdCount', 2)
            ->assertJsonPath('data.import.failedCount', 1)
            ->assertJsonPath('data.errors.0.row', 3)
            ->assertJsonPath('data.errors.0.message', 'Unknown category.');
    }

    public function test_show_is_forbidden_for_another_company(): void
    {
        $companyA = Company::factory()->supplier()->create();
        $import = ProductImport::factory()->create(['supplier_company_id' => $companyA->id]);

        $otherUser = User::factory()->supplier()->create();
        Sanctum::actingAs($otherUser);

        $this->getJson("/api/v1/products/imports/{$import->id}")
            ->assertStatus(403);
    }

    public function test_index_lists_only_own_company_imports(): void
    {
        $company = Company::factory()->supplier()->create();
        $user = User::factory()->create(['role' => UserRole::SUPPLIER, 'company_id' => $company->id]);
        ProductImport::factory()->count(2)->create(['supplier_company_id' => $company->id]);
        ProductImport::factory()->create(['supplier_company_id' => Company::factory()->supplier()->create()->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/products/imports')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
