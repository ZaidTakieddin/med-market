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
#[OA\Schema(
    schema: 'Error',
    title: 'Error envelope',
    description: 'Standardized error body returned by EVERY failed request. `error.title` is a short, '
        .'status-derived label and `error.message` is a human-readable explanation safe to show end users. '
        .'`errors` is present only on 422 validation failures (absent for business-rule 422s).',
    required: ['success', 'error'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(
            property: 'error',
            type: 'object',
            required: ['title', 'message'],
            properties: [
                new OA\Property(property: 'title', type: 'string', example: 'Not found'),
                new OA\Property(property: 'message', type: 'string', example: 'Resource not found.'),
            ]
        ),
        new OA\Property(
            property: 'errors',
            type: 'object',
            nullable: true,
            description: 'Field-keyed validation messages. Present only on 422 validation responses.',
            example: ['quantity' => ['The quantity must be at least 1.']]
        ),
    ]
)]
#[OA\Response(
    response: 'Unauthorized',
    description: 'Authentication required — the bearer token is missing, invalid, or expired.',
    content: new OA\JsonContent(
        ref: '#/components/schemas/Error',
        example: ['success' => false, 'error' => ['title' => 'Unauthenticated', 'message' => 'Authentication required.']]
    )
)]
#[OA\Response(
    response: 'Forbidden',
    description: 'Authenticated, but not permitted to perform this action (role, company scope, or policy).',
    content: new OA\JsonContent(
        ref: '#/components/schemas/Error',
        example: ['success' => false, 'error' => ['title' => 'Forbidden', 'message' => 'You do not have permission to perform this action.']]
    )
)]
#[OA\Response(
    response: 'NotFound',
    description: 'The resource does not exist, or is not visible to the authenticated company.',
    content: new OA\JsonContent(
        ref: '#/components/schemas/Error',
        example: ['success' => false, 'error' => ['title' => 'Not found', 'message' => 'Resource not found.']]
    )
)]
#[OA\Response(
    response: 'UnprocessableEntity',
    description: 'Input validation failed (then `errors` is present) OR a business rule was violated '
        .'(e.g. insufficient stock, credit limit exceeded, empty cart — `errors` is absent).',
    content: new OA\JsonContent(
        ref: '#/components/schemas/Error',
        example: ['success' => false, 'error' => ['title' => 'Validation failed', 'message' => 'The given data was invalid.'], 'errors' => ['field' => ['The field is required.']]]
    )
)]
#[OA\Response(
    response: 'TooManyRequests',
    description: 'Rate limit exceeded. Retry after the period given in the Retry-After response header.',
    content: new OA\JsonContent(
        ref: '#/components/schemas/Error',
        example: ['success' => false, 'error' => ['title' => 'Too many requests', 'message' => 'Too many requests. Please slow down.']]
    )
)]
#[OA\Response(
    response: 'Conflict',
    description: 'The request conflicts with the current state — typically the resource already exists '
        .'(duplicate buyer-supplier relationship, price-list entry, or client assignment).',
    content: new OA\JsonContent(
        ref: '#/components/schemas/Error',
        example: ['success' => false, 'error' => ['title' => 'Conflict', 'message' => 'A matching record already exists.']]
    )
)]
abstract class Controller
{
    use AuthorizesRequests;
}
