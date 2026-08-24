<?php

namespace Tests\Feature\Orders;

use App\Constants\ExportStatus;
use App\Jobs\ExportOrdersJob;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_an_export_queues_the_job_and_creates_a_record(): void
    {
        $supplierUser = User::factory()->supplier()->create();
        Sanctum::actingAs($supplierUser);
        Queue::fake();

        $response = $this->postJson('/api/v1/orders/exports', ['format' => 'csv']);

        $response->assertStatus(202)->assertJsonPath('data.status', ExportStatus::QUEUED);

        $export = OrderExport::firstOrFail();
        $this->assertSame($supplierUser->company_id, $export->company_id);
        $this->assertSame($supplierUser->id, $export->user_id);

        Queue::assertPushedOn('exports', ExportOrdersJob::class);
    }

    public function test_driver_cannot_request_exports(): void
    {
        Sanctum::actingAs(User::factory()->driver()->create());
        Queue::fake();

        $this->postJson('/api/v1/orders/exports', ['format' => 'csv'])->assertForbidden();
    }

    public function test_buyer_cannot_export_another_companys_orders(): void
    {
        $buyerUser = User::factory()->buyer()->create();
        Sanctum::actingAs($buyerUser);
        Queue::fake();

        $otherCompanyId = Company::factory()->supplier()->create()->id;

        $this->postJson('/api/v1/orders/exports', [
            'format' => 'csv',
            'company_id' => $otherCompanyId,
        ])->assertStatus(422);
    }

    public function test_admin_may_target_any_company(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Queue::fake();

        $targetCompany = Company::factory()->supplier()->create();

        $this->postJson('/api/v1/orders/exports', [
            'format' => 'json',
            'company_id' => $targetCompany->id,
        ])
            ->assertStatus(202)
            ->assertJsonPath('data.company.id', $targetCompany->id);

        $this->assertSame($targetCompany->id, (int) OrderExport::firstOrFail()->company_id);
    }

    public function test_exports_are_listed_for_own_company_only(): void
    {
        [$myExport] = $this->makeCompletedExport();
        OrderExport::factory()->completed()->create(); // another company's export

        Sanctum::actingAs(User::factory()->supplier()
            ->forCompany(Company::query()->findOrFail($myExport->company_id))->create());

        $response = $this->getJson('/api/v1/orders/exports')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertContains($myExport->id, $ids);
    }

    public function test_job_generates_a_csv_file_and_marks_export_completed(): void
    {
        Storage::fake();
        [$supplier, , $order] = $this->makeOrders();

        $export = OrderExport::factory()->create([
            'company_id' => $supplier->id,
            'format' => 'csv',
            'filters' => null,
        ]);

        (new ExportOrdersJob($export))->handle();

        $export->refresh();
        $this->assertSame(ExportStatus::COMPLETED, $export->status);
        $this->assertSame(1, $export->orders_count);
        $this->assertNotNull($export->file_path);
        Storage::assertExists((string) $export->file_path);

        $content = Storage::get((string) $export->file_path);
        $this->assertStringContainsString($order->order_number, (string) $content);
    }

    public function test_completed_exports_can_be_downloaded_by_same_company_users(): void
    {
        [$export] = $this->makeCompletedExport();

        Sanctum::actingAs(User::factory()->supplier()
            ->forCompany(Company::query()->findOrFail($export->company_id))->create());

        $response = $this->getJson("/api/v1/orders/exports/{$export->id}/download");

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    }

    public function test_download_before_completion_is_rejected_with_422(): void
    {
        $export = OrderExport::factory()->create(); // queued, no file

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson("/api/v1/orders/exports/{$export->id}/download")->assertStatus(422);
    }

    public function test_xlsx_export_generates_a_binary_workbook(): void
    {
        Storage::fake();
        [$supplier, , $order] = $this->makeOrders();

        $export = OrderExport::factory()->create([
            'company_id' => $supplier->id,
            'format' => 'xlsx',
        ]);

        (new ExportOrdersJob($export))->handle();

        $export->refresh();
        $this->assertSame(ExportStatus::COMPLETED, $export->status);
        $this->assertSame(1, $export->orders_count);
        Storage::assertExists((string) $export->file_path);
        // xlsx is a zip container — verify the magic header bytes.
        $bytes = substr((string) Storage::get((string) $export->file_path), 0, 2);
        $this->assertSame('PK', $bytes);
    }

    /**
     * Create an export whose file actually exists on the fake disk.
     *
     * @return array{0: OrderExport, 1: User}
     */
    private function makeCompletedExport(): array
    {
        Storage::fake();

        $export = OrderExport::factory()->completed()->create();
        Storage::put((string) $export->file_path, "Order Number\nORD-TEST-1\n");
        $owner = $export->user;

        return [$export, $owner];
    }

    /**
     * @return array{0: Company, 1: User, 2: Order}
     */
    private function makeOrders(): array
    {
        $supplier = Company::factory()->supplier()->create();
        $creator = User::factory()->buyer()->create();

        $order = Order::factory()->delivered()->create([
            'created_by' => $creator->id,
            'supplier_company_id' => $supplier->id,
        ]);

        return [$supplier, $creator, $order];
    }
}
