<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\BuyerClassification;
use App\Constants\CompanyType;
use App\Constants\DayOfWeek;
use App\Constants\DiscountType;
use App\Constants\DriverStatus;
use App\Constants\ExportStatus;
use App\Constants\InvoiceStatus;
use App\Constants\OfferType;
use App\Constants\OrderStatus;
use App\Constants\PaymentStatus;
use App\Constants\PaymentTerms;
use App\Constants\PricingType;
use App\Constants\ProductImportStatus;
use App\Constants\RegionPlanType;
use App\Constants\StockMovementType;
use App\Constants\SupplierClassification;
use App\Constants\UserRole;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Enums',
    description: 'System enums and constants. Use for dropdowns, validation, and form options.'
)]
class EnumsController extends BaseApiController
{
    /**
     * Get all enum types.
     */
    #[OA\Get(
        path: '/enums',
        summary: 'Get all enums',
        description: 'Returns all enum types used in the system. Useful for frontend dropdowns, form options, and validation. Each key contains an array of valid values.',
        tags: ['Enums'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'type',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['offerType', 'orderStatus', 'userRole', 'companyType', 'stockMovementType', 'discountType', 'supplierClassification', 'buyerClassification', 'pricingType', 'productImportStatus', 'paymentTerms', 'driverStatus', 'regionPlanType', 'dayOfWeek', 'invoiceStatus', 'paymentStatus']
                ),
                description: 'Optional. Return only this enum type.'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Enums retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'offerType',
                                    type: 'array',
                                    items: new OA\Items(type: 'string'),
                                    description: 'Offer types: buy_x_get_y, percentage_discount, fixed_discount, bundle_percentage, bundle_fixed'
                                ),
                                new OA\Property(
                                    property: 'orderStatus',
                                    type: 'array',
                                    items: new OA\Items(type: 'string'),
                                    description: 'Order statuses: pending, accepted, prepared, shipped, delivered, cancelled'
                                ),
                                new OA\Property(
                                    property: 'userRole',
                                    type: 'array',
                                    items: new OA\Items(type: 'string'),
                                    description: 'User roles: admin, supplier, buyer, driver, salesman'
                                ),
                                new OA\Property(
                                    property: 'companyType',
                                    type: 'array',
                                    items: new OA\Items(type: 'string'),
                                    description: 'Company types: supplier, buyer'
                                ),
                                new OA\Property(
                                    property: 'stockMovementType',
                                    type: 'array',
                                    items: new OA\Items(type: 'string'),
                                    description: 'Stock movement types: in, out, adjustment, return'
                                ),
                                new OA\Property(
                                    property: 'discountType',
                                    type: 'array',
                                    items: new OA\Items(type: 'string'),
                                    description: 'Discount types: percentage, fixed'
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $enums = [
            'offerType' => OfferType::all(),
            'orderStatus' => OrderStatus::all(),
            'userRole' => UserRole::all(),
            'companyType' => CompanyType::all(),
            'supplierClassification' => SupplierClassification::all(),
            'buyerClassification' => BuyerClassification::all(),
            'pricingType' => PricingType::all(),
            'productImportStatus' => ProductImportStatus::all(),
            'exportStatus' => ExportStatus::all(),
            'paymentTerms' => PaymentTerms::all(),
            'stockMovementType' => StockMovementType::all(),
            'discountType' => DiscountType::all(),
            'driverStatus' => DriverStatus::all(),
            'regionPlanType' => RegionPlanType::all(),
            'dayOfWeek' => DayOfWeek::all(),
            'invoiceStatus' => InvoiceStatus::all(),
            'paymentStatus' => PaymentStatus::all(),
        ];

        $type = $request->query('type');
        if ($type !== null && $type !== '') {
            $typeMap = [
                'offerType' => 'offerType',
                'offer_type' => 'offerType',
                'orderStatus' => 'orderStatus',
                'order_status' => 'orderStatus',
                'userRole' => 'userRole',
                'user_role' => 'userRole',
                'companyType' => 'companyType',
                'company_type' => 'companyType',
                'supplierClassification' => 'supplierClassification',
                'supplier_classification' => 'supplierClassification',
                'buyerClassification' => 'buyerClassification',
                'buyer_classification' => 'buyerClassification',
                'pricingType' => 'pricingType',
                'pricing_type' => 'pricingType',
                'productImportStatus' => 'productImportStatus',
                'product_import_status' => 'productImportStatus',
                'paymentTerms' => 'paymentTerms',
                'payment_terms' => 'paymentTerms',
                'stockMovementType' => 'stockMovementType',
                'stock_movement_type' => 'stockMovementType',
                'discountType' => 'discountType',
                'discount_type' => 'discountType',
                'driverStatus' => 'driverStatus',
                'driver_status' => 'driverStatus',
                'regionPlanType' => 'regionPlanType',
                'region_plan_type' => 'regionPlanType',
                'dayOfWeek' => 'dayOfWeek',
                'day_of_week' => 'dayOfWeek',
                'invoiceStatus' => 'invoiceStatus',
                'invoice_status' => 'invoiceStatus',
                'paymentStatus' => 'paymentStatus',
                'payment_status' => 'paymentStatus',
            ];
            $key = $typeMap[$type] ?? $type;
            if (isset($enums[$key])) {
                $enums = [$key => $enums[$key]];
            }
        }

        return $this->success($enums, 'Enums retrieved successfully');
    }
}
