<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Companies\CreditService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\BuyerSupplierRelationships\AdjustCreditRequest;
use App\Http\Requests\BuyerSupplierRelationships\CheckCreditRequest;
use App\Http\Requests\BuyerSupplierRelationships\CreateBuyerSupplierRelationshipRequest;
use App\Http\Requests\BuyerSupplierRelationships\UpdateBuyerSupplierRelationshipRequest;
use App\Http\Resources\BuyerSupplierRelationshipResource;
use App\Models\BuyerSupplierRelationship;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Buyer-Supplier Relationships',
    description: 'Manage buyer-supplier partnerships, payment terms, and credit limits. Suppliers can create relationships with buyers, set credit terms, and track outstanding balances.'
)]
class BuyerSupplierRelationshipController extends BaseApiController
{
    public function __construct(
        private CreditService $creditService
    ) {}

    #[OA\Get(
        path: '/buyer-supplier-relationships',
        summary: 'List relationships',
        description: 'Get buyer-supplier relationships. Admins see all. Suppliers see their buyer relationships. Buyers see their supplier relationships.',
        tags: ['Buyer-Supplier Relationships'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'buyer_company_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filter by buyer company'),
            new OA\Parameter(name: 'supplier_company_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filter by supplier company'),
            new OA\Parameter(name: 'is_approved', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Filter by approval status'),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Relationships retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $query = BuyerSupplierRelationship::with(['buyerCompany', 'supplierCompany']);

        if ($user->isAdmin()) {
            // Admin sees all
        } elseif ($user->isSupplier() || ($user->isSalesman() && $user->isSupplierSalesman())) {
            $query->where('supplier_company_id', $user->company_id);
        } else {
            $query->where('buyer_company_id', $user->company_id);
        }

        if ($request->has('buyer_company_id')) {
            $query->where('buyer_company_id', $request->buyer_company_id);
        }

        if ($request->has('supplier_company_id')) {
            $query->where('supplier_company_id', $request->supplier_company_id);
        }

        if ($request->has('is_approved')) {
            $query->where('is_approved', $request->boolean('is_approved'));
        }

        [$relationships, $pagination] = $this->paginateOrAll($query->latest(), $request);

        $additional = [
            'success' => true,
            'message' => 'Relationships retrieved successfully',
        ];
        if ($pagination !== null) {
            $additional['pagination'] = $pagination;
        }

        return BuyerSupplierRelationshipResource::collection($relationships)
            ->additional($additional)
            ->response();
    }

    #[OA\Post(
        path: '/buyer-supplier-relationships',
        summary: 'Create relationship',
        description: 'Create a buyer-supplier relationship. Only suppliers and admins can create. Prevents duplicate relationships.',
        tags: ['Buyer-Supplier Relationships'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['buyer_company_id', 'supplier_company_id', 'payment_terms'],
                properties: [
                    new OA\Property(property: 'buyer_company_id', type: 'integer', example: 1),
                    new OA\Property(property: 'supplier_company_id', type: 'integer', example: 2),
                    new OA\Property(property: 'payment_terms', type: 'string', enum: ['cash', 'net_15', 'net_30', 'net_60'], example: 'net_30'),
                    new OA\Property(property: 'credit_limit', type: 'number', format: 'float', nullable: true, example: 50000.00),
                    new OA\Property(property: 'is_approved', type: 'boolean', example: true),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'VIP customer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Relationship created successfully'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 409, ref: '#/components/responses/Conflict'),
        ]
    )]
    public function store(CreateBuyerSupplierRelationshipRequest $request): JsonResponse
    {
        $user = $this->currentUser();
        $this->authorize('create', BuyerSupplierRelationship::class);

        $buyerCompany = Company::findOrFail((int) $request->buyer_company_id);
        $supplierCompany = Company::findOrFail((int) $request->supplier_company_id);

        if (! $buyerCompany->isBuyer()) {
            return $this->error('The selected buyer company is not a buyer', null, 400);
        }

        if (! $supplierCompany->isSupplier()) {
            return $this->error('The selected supplier company is not a supplier', null, 400);
        }

        if (! $user->isAdmin() && $user->company_id !== $supplierCompany->id) {
            return $this->error('You can only create relationships for your own company', null, 403);
        }

        $exists = BuyerSupplierRelationship::where('buyer_company_id', $request->buyer_company_id)
            ->where('supplier_company_id', $request->supplier_company_id)
            ->exists();

        if ($exists) {
            return $this->error('A relationship between these companies already exists', null, 409);
        }

        $data = $request->validated();
        $data['current_balance'] = 0;

        if ($request->boolean('is_approved', false)) {
            $data['approved_at'] = now();
        }

        $relationship = BuyerSupplierRelationship::create($data);
        $relationship->load(['buyerCompany', 'supplierCompany']);

        return $this->success(
            new BuyerSupplierRelationshipResource($relationship),
            'Relationship created successfully',
            201
        );
    }

    #[OA\Get(
        path: '/buyer-supplier-relationships/{relationship}',
        summary: 'Get relationship details',
        description: 'Get a specific buyer-supplier relationship with credit details.',
        tags: ['Buyer-Supplier Relationships'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Relationship retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function show(BuyerSupplierRelationship $relationship): JsonResponse
    {
        $relationship->load(['buyerCompany', 'supplierCompany']);
        $this->authorize('view', $relationship);

        return $this->success(
            new BuyerSupplierRelationshipResource($relationship),
            'Relationship retrieved successfully'
        );
    }

    #[OA\Put(
        path: '/buyer-supplier-relationships/{relationship}',
        summary: 'Update relationship',
        description: 'Update payment terms, credit limit, approval status, or notes. Only the supplier side or admin can update.',
        tags: ['Buyer-Supplier Relationships'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'payment_terms', type: 'string', enum: ['cash', 'net_15', 'net_30', 'net_60']),
                    new OA\Property(property: 'credit_limit', type: 'number', format: 'float', nullable: true),
                    new OA\Property(property: 'is_approved', type: 'boolean'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Relationship updated successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function update(UpdateBuyerSupplierRelationshipRequest $request, BuyerSupplierRelationship $relationship): JsonResponse
    {
        $this->authorize('update', $relationship);

        $data = $request->validated();

        if (isset($data['is_approved']) && $data['is_approved'] && ! $relationship->is_approved) {
            $data['approved_at'] = now();
        } elseif (isset($data['is_approved']) && ! $data['is_approved']) {
            $data['approved_at'] = null;
        }

        $relationship->update($data);
        $relationship->load(['buyerCompany', 'supplierCompany']);

        return $this->success(
            new BuyerSupplierRelationshipResource($relationship),
            'Relationship updated successfully'
        );
    }

    #[OA\Delete(
        path: '/buyer-supplier-relationships/{relationship}',
        summary: 'Delete relationship',
        description: 'Delete a buyer-supplier relationship. Only the supplier side or admin can delete.',
        tags: ['Buyer-Supplier Relationships'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Relationship deleted successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function destroy(BuyerSupplierRelationship $relationship): JsonResponse
    {
        $this->authorize('delete', $relationship);

        if ((float) $relationship->current_balance > 0) {
            return $this->error('Cannot delete relationship with outstanding balance', null, 400);
        }

        $relationship->delete();

        return $this->success(null, 'Relationship deleted successfully');
    }

    #[OA\Post(
        path: '/buyer-supplier-relationships/{relationship}/approve',
        summary: 'Approve relationship',
        description: 'Approve a pending buyer-supplier relationship. Only supplier side or admin.',
        tags: ['Buyer-Supplier Relationships'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Relationship approved successfully'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function approve(BuyerSupplierRelationship $relationship): JsonResponse
    {
        $this->authorize('approve', $relationship);

        if ($relationship->is_approved) {
            return $this->error('This relationship is already approved', null, 400);
        }

        $relationship->update([
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        $relationship->load(['buyerCompany', 'supplierCompany']);

        return $this->success(
            new BuyerSupplierRelationshipResource($relationship),
            'Relationship approved successfully'
        );
    }

    #[OA\Post(
        path: '/buyer-supplier-relationships/{relationship}/reject',
        summary: 'Reject (revoke approval) relationship',
        description: 'Reject or revoke approval of a buyer-supplier relationship. Only supplier side or admin.',
        tags: ['Buyer-Supplier Relationships'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Relationship rejected successfully'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function reject(BuyerSupplierRelationship $relationship): JsonResponse
    {
        $this->authorize('approve', $relationship);

        if (! $relationship->is_approved) {
            return $this->error('This relationship is not currently approved', null, 400);
        }

        $relationship->update([
            'is_approved' => false,
            'approved_at' => null,
        ]);

        $relationship->load(['buyerCompany', 'supplierCompany']);

        return $this->success(
            new BuyerSupplierRelationshipResource($relationship),
            'Relationship rejected successfully'
        );
    }

    #[OA\Post(
        path: '/buyer-supplier-relationships/{relationship}/adjust-credit',
        summary: 'Adjust credit balance',
        description: 'Adjust the current outstanding balance. Use positive amount to increase (e.g. new order on credit), negative to decrease (e.g. payment received). Only supplier side or admin.',
        tags: ['Buyer-Supplier Relationships'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount', 'reason'],
                properties: [
                    new OA\Property(property: 'amount', type: 'number', format: 'float', description: 'Positive = increase balance (charge), negative = decrease balance (payment)', example: -500.00),
                    new OA\Property(property: 'reason', type: 'string', description: 'Reason for adjustment', example: 'Payment received - Invoice #INV-20260403-ABC'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Credit balance adjusted successfully'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function adjustCredit(AdjustCreditRequest $request, BuyerSupplierRelationship $relationship): JsonResponse
    {
        $this->authorize('adjustCredit', $relationship);

        // Balance mutation + limit checks happen atomically inside the domain service
        // (row-locked, transactional); violations throw DomainException → 422.
        ['relationship' => $relationship, 'previousBalance' => $previousBalance] = $this->creditService->adjustBalance(
            $relationship,
            (float) $request->amount,
            (string) $request->reason,
            (int) $this->currentUser()->id
        );

        $relationship->load(['buyerCompany', 'supplierCompany']);

        return $this->success(
            new BuyerSupplierRelationshipResource($relationship),
            'Credit balance adjusted successfully. Previous: '.number_format($previousBalance, 2).', New: '.number_format((float) $relationship->current_balance, 2)
        );
    }

    #[OA\Get(
        path: '/buyer-supplier-relationships/{relationship}/check-credit',
        summary: 'Check credit availability',
        description: 'Check if a given amount can be charged on credit for this relationship. Returns credit details and availability.',
        tags: ['Buyer-Supplier Relationships'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'amount', in: 'query', required: true, schema: new OA\Schema(type: 'number', format: 'float'), description: 'Amount to check'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Credit check completed'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function checkCredit(CheckCreditRequest $request, BuyerSupplierRelationship $relationship): JsonResponse
    {
        $this->authorize('view', $relationship);

        $amount = (float) $request->amount;
        $creditAvailable = $relationship->hasCreditAvailable($amount);
        $availableCredit = $relationship->credit_limit !== null
            ? max(0, (float) $relationship->credit_limit - (float) $relationship->current_balance)
            : null;

        return $this->success([
            'creditAvailable' => $creditAvailable,
            'requestedAmount' => $amount,
            'currentBalance' => (float) $relationship->current_balance,
            'creditLimit' => $relationship->credit_limit !== null ? (float) $relationship->credit_limit : null,
            'availableCredit' => $availableCredit,
            'isApproved' => $relationship->is_approved,
            'paymentTerms' => $relationship->payment_terms,
            'paymentDays' => $relationship->getPaymentDays(),
        ], 'Credit check completed');
    }
}
