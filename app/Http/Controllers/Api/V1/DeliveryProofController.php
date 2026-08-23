<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Orders\DeliveryProofService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Orders\StoreDeliveryProofRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Delivery Proofs', description: 'Photo/signature proof attachments for deliveries.')]
class DeliveryProofController extends BaseApiController
{
    public function __construct(
        private DeliveryProofService $deliveryProofService
    ) {}

    /**
     * Attach a delivery proof to an order.
     */
    #[OA\Post(
        path: '/orders/{id}/delivery-proof',
        summary: 'Upload a delivery proof',
        description: 'Attach a photo (handover picture) or signature image to an order as delivery evidence. Allowed for the assigned driver, either company on the order, or admins. Multipart form-data.',
        tags: ['Delivery Proofs'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'Order ID'),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'image', type: 'string', format: 'binary', description: 'Image file'),
                    new OA\Property(property: 'type', type: 'string', enum: ['photo', 'signature'], default: 'photo'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Delivery proof uploaded successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function store(StoreDeliveryProofRequest $request, Order $order): JsonResponse
    {
        $user = $this->currentUser();
        $this->authorize('view', $order);

        $proof = $this->deliveryProofService->attach(
            order: $order,
            user: $user,
            type: $request->input('type', 'photo'),
            file: $request->file('image'),
            notes: $request->input('notes'),
        );

        return $this->success([
            'id' => $proof->id,
            'type' => $proof->type,
            'url' => $proof->url,
            'notes' => $proof->notes,
            'uploadedBy' => $user->name,
        ], 'Delivery proof uploaded successfully', 201);
    }

    /**
     * List an order's delivery proofs.
     */
    #[OA\Get(
        path: '/orders/{id}/delivery-proof',
        summary: "List an order's delivery proofs",
        description: 'All photos/signatures attached to the order, oldest first. Visible to the buyer, supplier, assigned driver and admins.',
        tags: ['Delivery Proofs'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'Order ID'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Proofs retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function index(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $proofs = $this->deliveryProofService->forOrder($order)->map(fn ($proof): array => [
            'id' => $proof->id,
            'type' => $proof->type,
            'url' => $proof->url,
            'notes' => $proof->notes,
            'uploadedBy' => $proof->user?->name,
            'createdAt' => $proof->created_at?->toIso8601String(),
        ])->values();

        return $this->success(['proofs' => $proofs], 'Delivery proofs retrieved successfully');
    }
}
