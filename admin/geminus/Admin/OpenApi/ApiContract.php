<?php

declare(strict_types=1);

namespace Geminus\Admin\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Response(response: 'BadRequest', description: 'Malformed JSON or a non-object request body.', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
#[OA\Response(response: 'Unauthorized', description: 'Invalid or expired Access Token, or an unavailable account.', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
#[OA\Response(response: 'Forbidden', description: 'The account permission or token scope does not allow the operation.', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
#[OA\Response(response: 'UnsupportedMediaType', description: 'Content-Type must be application/json.', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
#[OA\Response(response: 'ValidationError', description: 'Invalid fields or unknown input fields.', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
#[OA\Response(response: 'TooManyRequests', description: 'API rate limit exceeded.', headers: [new OA\Header(header: 'Retry-After', description: 'Seconds before retrying.', schema: new OA\Schema(type: 'integer'))], content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
#[OA\Response(response: 'ServerError', description: 'Internal failure; exception details are not exposed.', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
#[OA\Schema(
    schema: 'ApiError',
    type: 'object',
    required: ['status', 'error', 'messages'],
    properties: [
        new OA\Property(property: 'status', type: 'integer', description: 'HTTP status code'),
        new OA\Property(property: 'error', type: 'integer', description: 'HTTP status code'),
        new OA\Property(property: 'messages', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string'), description: 'General errors use the error key; validation errors use field names.'),
    ],
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer', description: 'Access Token. Both the account permission and the token scope must allow the operation.')]
class ApiContract
{
}
