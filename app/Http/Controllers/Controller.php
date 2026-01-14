<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Med Market API',
    description: 'B2B Medical Delivery System API Documentation'
)]
#[OA\Server(
    url: '/api/v1',
    description: 'API Server'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Enter your bearer token in the format: Bearer {token}'
)]
abstract class Controller
{
    //
}
