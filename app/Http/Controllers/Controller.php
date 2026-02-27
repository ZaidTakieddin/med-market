<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Med Market API',
    description: 'B2B Medical Delivery System API Documentation'
)]
#[OA\Server(
        url: '/api/v1',
        description: 'API Server',
        variables: []
    )]
#[OA\SecurityScheme(
        securityScheme: 'sanctum',
        type: 'http',
        scheme: 'bearer',
        bearerFormat: 'JWT',
        description: 'Enter your bearer token in the format: Bearer {token}'
    )]
#[OA\Schema(
    schema: 'CartItem',
    description: 'Cart item with product, pricing, and optional applied offer',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'product', type: 'object', description: 'Product object'),
        new OA\Property(property: 'quantity', type: 'integer', example: 3),
        new OA\Property(property: 'unitPrice', type: 'number', format: 'float', description: 'Current unit price (after offer if applied)'),
        new OA\Property(property: 'totalPrice', type: 'number', format: 'float', description: 'Current total (quantity × unitPrice, after offer if applied)'),
        new OA\Property(property: 'baseUnitPrice', type: 'number', format: 'float', description: 'Product base price before any offer (for showing strikethrough/original price)'),
        new OA\Property(property: 'baseTotalPrice', type: 'number', format: 'float', description: 'Base total before offer (quantity × baseUnitPrice). When offer applied, use to show savings.'),
        new OA\Property(property: 'appliedOffer', type: 'object', nullable: true, description: 'Offer object when an offer is applied'),
        new OA\Property(property: 'appliedOfferId', type: 'integer', nullable: true, description: 'ID of applied offer. Null when no offer.'),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'Offer',
    description: 'Product offer or promotion',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'offerType', type: 'string', enum: ['buy_x_get_y', 'percentage_discount', 'fixed_discount', 'bundle_percentage', 'bundle_fixed']),
        new OA\Property(property: 'discountValue', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'quantityRequired', type: 'integer', description: 'Minimum quantity to qualify'),
        new OA\Property(property: 'quantityFree', type: 'integer', nullable: true, description: 'For buy_x_get_y: free items per qualifying set'),
        new OA\Property(property: 'startDate', type: 'string', format: 'date-time'),
        new OA\Property(property: 'endDate', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'isActive', type: 'boolean'),
        new OA\Property(property: 'isCurrentlyActive', type: 'boolean', description: 'Within date range and active'),
    ]
)]
abstract class Controller
{
    use AuthorizesRequests;
}
