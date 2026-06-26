<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\PaymentTerms;
use App\Domain\Companies\CreditService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Invoices\MarkInvoicePaidRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Invoices', description: 'Invoice management endpoints')]
class InvoiceController extends BaseApiController
{
    public function __construct(
        private CreditService $creditService
    ) {}

    /**
     * List invoices.
     */
    #[OA\Get(
        path: '/invoices',
        summary: 'List invoices',
        description: 'Get a paginated list of invoices. Buyers see invoices for their orders, suppliers see invoices for orders they supplied.',
        tags: ['Invoices'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'order_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filter by order ID'),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['generated', 'sent', 'paid', 'overdue', 'cancelled']), description: 'Filter by invoice status'),
            new OA\Parameter(name: 'payment_status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'paid', 'partial', 'overdue', 'refunded']), description: 'Filter by payment status'),
            new OA\Parameter(name: 'from_date', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Filter invoices generated from this date'),
            new OA\Parameter(name: 'to_date', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Filter invoices generated until this date'),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15), description: 'Items per page. Use "all" for no pagination.'),
            new OA\Parameter(name: 'paginate', in: 'query', schema: new OA\Schema(type: 'string', enum: ['true', 'false']), description: 'Use "false" to get all results without pagination'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invoices retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $this->currentUser();
            $query = Invoice::with(['order.driver', 'buyerCompany', 'supplierCompany']);

            if ($user->isBuyer() || ($user->isSalesman() && $user->isIndependentSalesman())) {
                $query->where('buyer_company_id', $user->company_id);
            } elseif ($user->isSalesman() && $user->isSupplierSalesman()) {
                $query->where(function ($q) use ($user) {
                    $q->where('supplier_company_id', $user->company_id)
                        ->orWhereHas('order', fn ($oq) => $oq->where('placed_by_salesman_id', $user->id));
                });
            } elseif ($user->isSupplier()) {
                $query->where('supplier_company_id', $user->company_id);
            }
            // Admins see all invoices

            // Filter by order
            if ($request->has('order_id')) {
                $query->where('order_id', $request->order_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by payment status
            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            // Filter by date range
            if ($request->has('from_date')) {
                $query->whereDate('generated_at', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->whereDate('generated_at', '<=', $request->to_date);
            }

            [$invoices, $pagination] = $this->paginateOrAll($query->latest('generated_at'), $request);

            $additional = [
                'success' => true,
                'message' => 'Invoices retrieved successfully',
            ];
            if ($pagination !== null) {
                $additional['pagination'] = $pagination;
            }

            return InvoiceResource::collection($invoices)
                ->additional($additional)
                ->response();
        } catch (\Exception $e) {
            Log::error('Invoice list error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to retrieve invoices. Please try again later.', null, 500);
        }
    }

    /**
     * Get a specific invoice.
     */
    #[OA\Get(
        path: '/invoices/{id}',
        summary: 'Get invoice',
        description: 'Get details of a specific invoice',
        tags: ['Invoices'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invoice retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Invoice does not belong to your company'),
            new OA\Response(response: 404, description: 'Invoice not found'),
        ]
    )]
    public function show(Invoice $invoice): JsonResponse
    {
        $user = $this->currentUser();

        if (! $this->canAccessInvoice($user, $invoice)) {
            return $this->error('Unauthorized', null, 403);
        }

        $invoice->load([
            'order.driver',
            'order.items.product',
            'order.items.appliedOffer',
            'buyerCompany',
            'supplierCompany',
        ]);

        return $this->success(new InvoiceResource($invoice));
    }

    /**
     * Get invoice for a specific order.
     */
    #[OA\Get(
        path: '/orders/{order_id}/invoice',
        summary: 'Get invoice for order',
        description: 'Get the invoice associated with a specific order',
        tags: ['Invoices'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'order_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invoice retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Order does not belong to your company'),
            new OA\Response(response: 404, description: 'Order or invoice not found'),
        ]
    )]
    public function getByOrder(Order $order): JsonResponse
    {
        $user = $this->currentUser();

        if (! $this->canAccessOrder($user, $order)) {
            return $this->error('Unauthorized', null, 403);
        }

        $invoice = Invoice::where('order_id', $order->id)->first();

        if (! $invoice) {
            return $this->error('Invoice not found for this order', null, 404);
        }

        $invoice->load([
            'order.driver',
            'order.items.product',
            'order.items.appliedOffer',
            'buyerCompany',
            'supplierCompany',
        ]);

        return $this->success(new InvoiceResource($invoice));
    }

    /**
     * Download invoice file.
     */
    #[OA\Get(
        path: '/invoices/{id}/download',
        summary: 'Download invoice',
        description: 'Download the invoice file',
        tags: ['Invoices'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invoice file downloaded successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Invoice not found'),
        ]
    )]
    public function download(Invoice $invoice): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        $user = $this->currentUser();

        if (! $this->canAccessInvoice($user, $invoice)) {
            return $this->error('Unauthorized', null, 403);
        }

        if (! Storage::exists($invoice->file_path)) {
            return $this->error('Invoice file not found', null, 404);
        }

        return Storage::download($invoice->file_path, "{$invoice->invoice_number}.{$invoice->file_format}");
    }

    /**
     * Mark invoice as sent.
     */
    #[OA\Post(
        path: '/invoices/{id}/send',
        summary: 'Mark invoice as sent',
        description: 'Mark an invoice as sent (suppliers only)',
        tags: ['Invoices'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invoice marked as sent successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Only suppliers can mark invoices as sent'),
            new OA\Response(response: 404, description: 'Invoice not found'),
        ]
    )]
    public function markAsSent(Invoice $invoice): JsonResponse
    {
        $this->authorize('send', $invoice);

        $invoice->markAsSent();

        return $this->success(new InvoiceResource($invoice->load(['order.driver', 'buyerCompany', 'supplierCompany'])), 'Invoice marked as sent successfully');
    }

    /**
     * Mark invoice as paid.
     */
    #[OA\Post(
        path: '/invoices/{id}/pay',
        summary: 'Mark invoice as paid',
        description: 'Mark an invoice as paid. Only the issuing supplier (or admin) may confirm payment — a buyer cannot mark their own invoice paid (it would release their trade credit).',
        tags: ['Invoices'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'amount', type: 'number', format: 'float', description: 'Payment amount (defaults to full amount if not provided)', example: 1000.00),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, description: 'Payment notes', example: 'Payment via bank transfer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Invoice marked as paid successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Invoice not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function markAsPaid(MarkInvoicePaidRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('pay', $invoice);

        $amount = $request->amount ?? $invoice->total_amount;

        if ($amount > $invoice->total_amount) {
            return $this->error('Payment amount cannot exceed invoice total', null, 422);
        }

        $previouslyPaid = (float) $invoice->paid_amount;

        $invoice->markAsPaid($amount);

        // Release the newly-paid portion of a net-term order's reserved trade credit.
        $newlyPaid = max(0, (float) $amount - $previouslyPaid);
        $order = $invoice->order_id ? Order::find($invoice->order_id) : null;
        if ($newlyPaid > 0 && $order && $order->payment_method !== PaymentTerms::CASH) {
            $this->creditService->release($invoice->buyer_company_id, $invoice->supplier_company_id, $newlyPaid);
        }

        if ($request->has('notes')) {
            $invoice->update(['notes' => $request->notes]);
        }

        return $this->success(new InvoiceResource($invoice->load(['order.driver', 'buyerCompany', 'supplierCompany'])), 'Invoice marked as paid successfully');
    }

    private function canAccessInvoice(User $user, Invoice $invoice): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBuyer() || ($user->isSalesman() && $user->isIndependentSalesman())) {
            return $invoice->buyer_company_id === $user->company_id;
        }

        if ($user->isSalesman() && $user->isSupplierSalesman()) {
            return $invoice->supplier_company_id === $user->company_id
                || ($invoice->order && $invoice->order->placed_by_salesman_id === $user->id);
        }

        if ($user->isSupplier()) {
            return $invoice->supplier_company_id === $user->company_id;
        }

        return false;
    }

    private function canAccessOrder(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBuyer() || ($user->isSalesman() && $user->isIndependentSalesman())) {
            return $order->buyer_company_id === $user->company_id;
        }

        if ($user->isSalesman() && $user->isSupplierSalesman()) {
            return $order->supplier_company_id === $user->company_id
                || $order->placed_by_salesman_id === $user->id;
        }

        if ($user->isSupplier()) {
            return $order->supplier_company_id === $user->company_id;
        }

        return false;
    }
}
