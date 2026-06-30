<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ProductImportStatus;
use App\Domain\Products\Import\ProductsSampleExport;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Products\ImportProductsRequest;
use App\Http\Resources\ProductImportErrorResource;
use App\Http\Resources\ProductImportResource;
use App\Jobs\ImportProductsJob;
use App\Models\ProductImport;
use App\Models\ProductImportError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[OA\Tag(name: 'Product Import', description: 'Bulk product import from Excel/CSV')]
class ProductImportController extends BaseApiController
{
    /**
     * Download a sample import file.
     */
    #[OA\Get(
        path: '/products/import-sample',
        summary: 'Download product import sample',
        description: 'Download a ready-to-edit .xlsx sample with the exact headers the importer expects and a few example rows. Replace the example rows with your products. Columns: '.'name, sku, pharmacist_price (required); description, category (name), manufacturer (name), barcode, unit, wholesale_price, wholesale_min_qty, customer_price, discount, discount_type, stock_quantity, min_stock_level, is_active. Note: stock_quantity applies only when creating a NEW product; on update (matched by SKU) stock is left unchanged.',
        tags: ['Product Import'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sample .xlsx file download'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function sample(): BinaryFileResponse
    {
        return Excel::download(new ProductsSampleExport, 'products_import_sample.xlsx');
    }

    /**
     * Start a product import.
     */
    #[OA\Post(
        path: '/products/import',
        summary: 'Import products from Excel/CSV',
        description: 'Upload an .xlsx/.csv file to bulk create/update products. Existing products are matched by SKU (within your company) and updated; new SKUs are created. Categories and manufacturers are matched by NAME (unknown names error that row). Processing is asynchronous — poll GET /products/imports/{id} for status and per-row errors.',
        tags: ['Product Import'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'Excel (.xlsx, .xls) or CSV file, max 10 MB'),
                        new OA\Property(property: 'supplier_company_id', type: 'integer', description: 'Required for admins. Ignored for suppliers (uses their company).'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 202, description: 'Import accepted and queued. Returns the import record (status=pending).'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function store(ImportProductsRequest $request): JsonResponse
    {
        $this->authorize('create', ProductImport::class);

        $user = $this->currentUser();
        $supplierCompanyId = $user->isSupplier()
            ? (int) $user->company_id
            : (int) $request->supplier_company_id;

        $file = $request->file('file');
        $storedPath = $file->store('imports/products', 'local');

        $import = ProductImport::create([
            'supplier_company_id' => $supplierCompanyId,
            'user_id' => $user->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'status' => ProductImportStatus::PENDING,
        ]);

        ImportProductsJob::dispatch($import->id);

        return $this->success(
            new ProductImportResource($import),
            'Import accepted and queued for processing',
            202
        );
    }

    /**
     * List product imports.
     */
    #[OA\Get(
        path: '/products/imports',
        summary: 'List product imports',
        description: 'List past product imports for your company (admins see all). Paginated.',
        tags: ['Product Import'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Imports retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductImport::class);

        $user = $this->currentUser();
        $query = ProductImport::query()->with('supplierCompany')->latest();

        if (! $user->isAdmin()) {
            $query->where('supplier_company_id', $user->company_id);
        }

        [$imports, $pagination] = $this->paginateOrAll($query, $request);

        $additional = [
            'success' => true,
            'message' => 'Imports retrieved successfully',
        ];
        if ($pagination !== null) {
            $additional['pagination'] = $pagination;
        }

        return ProductImportResource::collection($imports)
            ->additional($additional)
            ->response();
    }

    /**
     * Get a product import's status and per-row errors.
     */
    #[OA\Get(
        path: '/products/imports/{import}',
        summary: 'Get product import status',
        description: 'Get an import\'s status, counts (created/updated/failed), and a paginated list of per-row errors.',
        tags: ['Product Import'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'import', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 50), description: 'Errors per page'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Import retrieved successfully (data.import + data.errors)'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function show(Request $request, ProductImport $import): JsonResponse
    {
        $this->authorize('view', $import);

        $import->load('supplierCompany');

        [$errors, $pagination] = $this->paginateOrAll(
            ProductImportError::query()->where('product_import_id', $import->id)->orderBy('row_number'),
            $request,
            50
        );

        $data = [
            'import' => new ProductImportResource($import),
            'errors' => ProductImportErrorResource::collection($errors),
        ];
        if ($pagination !== null) {
            $data['errorsPagination'] = $pagination;
        }

        return $this->success($data, 'Import retrieved successfully');
    }
}
