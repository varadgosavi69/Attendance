<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'College Attendance API',
    description: 'Versioned REST API for marking attendance, managing students/subjects, and generating detention & dashboard reports.',
)]
#[OA\Server(url: '/api/v1', description: 'Default API server')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Enter the JWT access token returned by /api/v1/auth/login',
)]
#[OA\Schema(
    schema: 'ApiError',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(
            property: 'error',
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'VALIDATION_FAILED'),
                new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
                new OA\Property(property: 'details', type: 'object', nullable: true),
            ],
            type: 'object',
        ),
    ],
    type: 'object',
)]
abstract class Controller
{
    //
}
