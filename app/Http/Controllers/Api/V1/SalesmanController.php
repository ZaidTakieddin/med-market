<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\BuyerClassification;
use App\Constants\UserRole;
use App\Domain\Orders\OrderCreationService;
use App\Domain\Pricing\PricingService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Salesmen\AssignSalesmanClientRequest;
use App\Http\Requests\Salesmen\AssignSalesmanRegionsRequest;
use App\Http\Requests\Salesmen\PlaceOrderOnBehalfRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\RegionResource;
use App\Http\Resources\SalesmanClientResource;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesmanClient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Salesmen',
    description: 'Salesman management: listing, region assignment, client (pharmacy) management, and on-behalf ordering. Supplier-associated salesmen belong to a supplier company. Independent salesmen have their own buyer company.'
)]
class SalesmanController extends BaseApiController
{
    public function __construct(
        private OrderCreationService $orderCreationService,
        private PricingService $pricingService
    ) {}

    #[OA\Get(
        path: '/salesmen',
        summary: 'List salesmen',
        description: 'List salesman users. Admins see all. Suppliers see their company salesmen. Salesmen see themselves.',
        tags: ['Salesmen'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'company_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filter by company'),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Search by name, email, or phone'),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Salesmen retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $query = User::where('role', UserRole::SALESMAN)->with('company');

            if ($user->isAdmin()) {
                // Admin sees all
            } elseif ($user->isSupplier()) {
                $query->where('company_id', $user->company_id);
            } elseif ($user->isSalesman()) {
                $query->where('id', $user->id);
            } else {
                return $this->error('You do not have access to salesmen', null, 403);
            }

            if ($request->has('company_id')) {
                $query->where('company_id', $request->company_id);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereLike('name', "%{$search}%")
                        ->orWhereLike('email', "%{$search}%")
                        ->orWhereLike('phone', "%{$search}%");
                });
            }

            [$salesmen, $pagination] = $this->paginateOrAll($query->latest(), $request);

            $additional = ['success' => true, 'message' => 'Salesmen retrieved successfully'];
            if ($pagination !== null) {
                $additional['pagination'] = $pagination;
            }

            return UserResource::collection($salesmen)
                ->additional($additional)
                ->response();
        } catch (\Exception $e) {
            Log::error('Salesman list error', ['message' => $e->getMessage()]);

            return $this->error('Failed to retrieve salesmen. Please try again later.', null, 500);
        }
    }

    #[OA\Get(
        path: '/salesmen/{id}',
        summary: 'Get salesman details',
        description: 'Get salesman profile with company, regions, and client counts.',
        tags: ['Salesmen'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Salesman retrieved successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Salesman not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $salesman = User::where('role', UserRole::SALESMAN)
            ->with(['company', 'salesmanRegions.city', 'salesmanClients.pharmacyCompany'])
            ->findOrFail($id);
        $this->authorize('accessSalesman', $salesman);

        try {
            $data = (new UserResource($salesman))->toArray(request());
            $data['regions'] = RegionResource::collection($salesman->salesmanRegions);
            $data['clients'] = SalesmanClientResource::collection($salesman->salesmanClients);
            $data['clientCount'] = $salesman->salesmanClients()->where('is_active', true)->count();
            $data['orderCount'] = $salesman->salesmanOrders()->count();
            $data['isSupplierSalesman'] = $salesman->isSupplierSalesman();
            $data['isIndependentSalesman'] = $salesman->isIndependentSalesman();

            return $this->success($data, 'Salesman retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Salesman show error', ['message' => $e->getMessage()]);

            return $this->error('Failed to retrieve salesman. Please try again later.', null, 500);
        }
    }

    #[OA\Get(
        path: '/salesmen/{id}/regions',
        summary: 'Get salesman regions',
        description: 'Get all regions assigned to a salesman.',
        tags: ['Salesmen'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Regions retrieved successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Salesman not found'),
        ]
    )]
    public function regions(int $id): JsonResponse
    {
        $salesman = User::where('role', UserRole::SALESMAN)->findOrFail($id);
        $this->authorize('accessSalesman', $salesman);

        try {
            $regions = $salesman->salesmanRegions()->with('city')->get();

            return $this->success(
                RegionResource::collection($regions),
                'Salesman regions retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Salesman regions error', ['message' => $e->getMessage()]);

            return $this->error('Failed to retrieve regions. Please try again later.', null, 500);
        }
    }

    #[OA\Post(
        path: '/salesmen/{id}/regions',
        summary: 'Assign regions to salesman',
        description: 'Assign regions to a salesman. Only suppliers (for their salesmen) and admins can assign regions. Syncs the region list (replaces existing assignments).',
        tags: ['Salesmen'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['region_ids'],
                properties: [
                    new OA\Property(property: 'region_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Regions assigned successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Salesman not found'),
        ]
    )]
    public function assignRegions(AssignSalesmanRegionsRequest $request, int $id): JsonResponse
    {
        $salesman = User::where('role', UserRole::SALESMAN)->findOrFail($id);
        $this->authorize('manageSalesman', $salesman);

        try {
            $salesman->salesmanRegions()->sync($request->region_ids);
            $regions = $salesman->salesmanRegions()->with('city')->get();

            return $this->success(
                RegionResource::collection($regions),
                'Regions assigned to salesman successfully'
            );
        } catch (\Exception $e) {
            Log::error('Salesman assign regions error', ['message' => $e->getMessage()]);

            return $this->error('Failed to assign regions. Please try again later.', null, 500);
        }
    }

    #[OA\Delete(
        path: '/salesmen/{id}/regions/{regionId}',
        summary: 'Remove region from salesman',
        description: 'Remove a specific region from a salesman.',
        tags: ['Salesmen'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'regionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Region removed successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Salesman or region not found'),
        ]
    )]
    public function removeRegion(int $id, int $regionId): JsonResponse
    {
        $salesman = User::where('role', UserRole::SALESMAN)->findOrFail($id);
        $this->authorize('manageSalesman', $salesman);

        try {
            $detached = $salesman->salesmanRegions()->detach($regionId);
            if ($detached === 0) {
                return $this->error('Region was not assigned to this salesman', null, 404);
            }

            return $this->success(null, 'Region removed from salesman successfully');
        } catch (\Exception $e) {
            Log::error('Salesman remove region error', ['message' => $e->getMessage()]);

            return $this->error('Failed to remove region. Please try again later.', null, 500);
        }
    }

    #[OA\Get(
        path: '/salesmen/{id}/clients',
        summary: 'Get salesman clients (pharmacies)',
        description: 'Get all pharmacy clients assigned to a salesman.',
        tags: ['Salesmen'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Filter by active status'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Clients retrieved successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Salesman not found'),
        ]
    )]
    public function clients(Request $request, int $id): JsonResponse
    {
        $salesman = User::where('role', UserRole::SALESMAN)->findOrFail($id);
        $this->authorize('accessSalesman', $salesman);

        try {
            $query = $salesman->salesmanClients()->with(['pharmacyCompany', 'salesmanUser', 'salesmanCompany']);

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $clients = $query->latest()->get();

            return $this->success(
                SalesmanClientResource::collection($clients),
                'Salesman clients retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Salesman clients error', ['message' => $e->getMessage()]);

            return $this->error('Failed to retrieve clients. Please try again later.', null, 500);
        }
    }

    #[OA\Post(
        path: '/salesmen/{id}/clients',
        summary: 'Assign client (pharmacy) to salesman',
        description: 'Assign a pharmacy company as a client of a salesman. The pharmacy must have buyer_classification = pharmacy. Prevents duplicate assignments.',
        tags: ['Salesmen'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['pharmacy_company_id'],
                properties: [
                    new OA\Property(property: 'pharmacy_company_id', type: 'integer', example: 5),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Client assigned successfully'),
            new OA\Response(response: 400, description: 'Company is not a pharmacy'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 409, description: 'Client already assigned'),
        ]
    )]
    public function assignClient(AssignSalesmanClientRequest $request, int $id): JsonResponse
    {
        $salesman = User::where('role', UserRole::SALESMAN)->findOrFail($id);
        $this->authorize('manageSalesman', $salesman);

        try {
            $pharmacy = Company::findOrFail((int) $request->pharmacy_company_id);
            if ($pharmacy->buyer_classification !== BuyerClassification::PHARMACY) {
                return $this->error('The selected company is not a pharmacy', null, 400);
            }

            $exists = SalesmanClient::where('salesman_user_id', $salesman->id)
                ->where('pharmacy_company_id', $pharmacy->id)
                ->exists();

            if ($exists) {
                return $this->error('This pharmacy is already assigned to this salesman', null, 409);
            }

            $client = SalesmanClient::create([
                'salesman_user_id' => $salesman->id,
                'salesman_company_id' => $salesman->isSupplierSalesman() ? $salesman->company_id : null,
                'pharmacy_company_id' => $pharmacy->id,
                'is_active' => true,
                'assigned_at' => now(),
            ]);

            $client->load(['pharmacyCompany', 'salesmanUser', 'salesmanCompany']);

            return $this->success(
                new SalesmanClientResource($client),
                'Client assigned to salesman successfully',
                201
            );
        } catch (\Exception $e) {
            Log::error('Salesman assign client error', ['message' => $e->getMessage()]);

            return $this->error('Failed to assign client. Please try again later.', null, 500);
        }
    }

    #[OA\Delete(
        path: '/salesmen/{id}/clients/{clientId}',
        summary: 'Remove client from salesman',
        description: 'Deactivate a pharmacy client assignment. Does not delete the record, marks as inactive.',
        tags: ['Salesmen'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'clientId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Client removed successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Salesman or client not found'),
        ]
    )]
    public function removeClient(int $id, int $clientId): JsonResponse
    {
        $salesman = User::where('role', UserRole::SALESMAN)->findOrFail($id);
        $this->authorize('manageSalesman', $salesman);

        try {
            $client = SalesmanClient::where('salesman_user_id', $salesman->id)
                ->where('id', $clientId)
                ->firstOrFail();

            $client->update(['is_active' => false]);

            return $this->success(null, 'Client removed from salesman successfully');
        } catch (\Exception $e) {
            Log::error('Salesman remove client error', ['message' => $e->getMessage()]);

            return $this->error('Failed to remove client. Please try again later.', null, 500);
        }
    }

    #[OA\Get(
        path: '/salesmen/{id}/orders',
        summary: 'Get orders placed by salesman',
        description: 'Get all orders placed by a salesman on behalf of buyers.',
        tags: ['Salesmen'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Orders retrieved successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Salesman not found'),
        ]
    )]
    public function orders(Request $request, int $id): JsonResponse
    {
        $salesman = User::where('role', UserRole::SALESMAN)->findOrFail($id);
        $this->authorize('accessSalesman', $salesman);

        try {
            $query = Order::where('placed_by_salesman_id', $salesman->id)
                ->with([
                    'buyerCompany',
                    'supplierCompany',
                    'items.product',
                    'salesman',
                ])
                ->latest();

            [$orders, $pagination] = $this->paginateOrAll($query, $request);

            $additional = ['success' => true, 'message' => 'Salesman orders retrieved successfully'];
            if ($pagination !== null) {
                $additional['pagination'] = $pagination;
            }

            return OrderResource::collection($orders)
                ->additional($additional)
                ->response();
        } catch (\Exception $e) {
            Log::error('Salesman orders error', ['message' => $e->getMessage()]);

            return $this->error('Failed to retrieve salesman orders. Please try again later.', null, 500);
        }
    }

    #[OA\Post(
        path: '/salesmen/{id}/place-order',
        summary: 'Place order on behalf of a buyer',
        description: 'A supplier-associated salesman places an order on behalf of one of their client pharmacies. The salesman must have the pharmacy assigned as a client. Prices are resolved via PricingService using the buyer company context. The order records placed_by_salesman_id for tracking.',
        tags: ['Salesmen'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['buyer_company_id', 'supplier_company_id', 'items'],
                properties: [
                    new OA\Property(property: 'buyer_company_id', type: 'integer', example: 5, description: 'The pharmacy to place the order for'),
                    new OA\Property(property: 'supplier_company_id', type: 'integer', example: 2, description: 'The supplier company'),
                    new OA\Property(property: 'items', type: 'array', items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'product_id', type: 'integer', example: 1),
                            new OA\Property(property: 'quantity', type: 'integer', example: 10),
                            new OA\Property(property: 'unit_price', type: 'number', format: 'float', nullable: true),
                            new OA\Property(property: 'notes', type: 'string', nullable: true),
                        ]
                    )),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                    new OA\Property(property: 'tax_rate', type: 'number', format: 'float', example: 0),
                    new OA\Property(property: 'payment_method', type: 'string', enum: ['cash', 'net_15', 'net_30', 'net_60'], nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Order placed on behalf of buyer successfully'),
            new OA\Response(response: 400, description: 'Pharmacy not assigned to this salesman'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Salesman not found'),
        ]
    )]
    public function placeOrder(PlaceOrderOnBehalfRequest $request, int $id): JsonResponse
    {
        $salesman = User::where('role', UserRole::SALESMAN)->findOrFail($id);
        $this->authorize('accessSalesman', $salesman);

        try {
            $user = auth()->user();

            $buyerCompany = Company::findOrFail((int) $request->buyer_company_id);
            if (! $buyerCompany->isBuyer()) {
                return $this->error('The selected company is not a buyer', null, 400);
            }

            $isClient = SalesmanClient::where('salesman_user_id', $salesman->id)
                ->where('pharmacy_company_id', $buyerCompany->id)
                ->where('is_active', true)
                ->exists();

            if (! $isClient && ! $user->isAdmin()) {
                return $this->error('This pharmacy is not assigned as a client of this salesman', null, 400);
            }

            $items = [];
            foreach ($request->items as $item) {
                $product = Product::findOrFail((int) $item['product_id']);

                if ($item['unit_price'] ?? null) {
                    $unitPrice = $item['unit_price'];
                    $pricingType = $item['pricing_type'] ?? null;
                } else {
                    $priceResult = $this->pricingService->resolve($product, $buyerCompany, $item['quantity']);
                    $unitPrice = $priceResult->unitPrice;
                    $pricingType = $priceResult->pricingType;
                }

                $items[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'pricing_type' => $pricingType,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            $order = $this->orderCreationService->create(
                buyerCompanyId: $buyerCompany->id,
                supplierCompanyId: $request->supplier_company_id,
                createdBy: $salesman->id,
                items: $items,
                notes: $request->notes,
                taxRate: $request->tax_rate ?? 0,
                paymentMethod: $request->payment_method,
            );

            // Payment terms + credit reservation are handled inside OrderCreationService.
            $order->update(['placed_by_salesman_id' => $salesman->id]);

            return $this->success(
                new OrderResource($order->load([
                    'buyerCompany.region.city',
                    'supplierCompany',
                    'salesman',
                    'items.product.category',
                    'items.product.manufacturer',
                    'items.product.supplierCompany',
                ])),
                'Order placed on behalf of buyer successfully',
                201
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            Log::error('Salesman place order error', ['message' => $e->getMessage()]);

            return $this->error('Failed to place order. Please try again later.', null, 500);
        }
    }
}
