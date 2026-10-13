<?php

declare(strict_types=1);

namespace Modules\Announcements\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use Modules\Announcements\Libraries\InvalidAnnouncementInput;
use OpenApi\Attributes as OA;
use Throwable;

class ApiAnnouncements extends ResourceController
{
    #[OA\Post(
        path: '/api/v1/announcements',
        operationId: 'publishAnnouncement',
        summary: 'Create and publish an announcement',
        description: 'Requires announcements.manage on both the account and token scope. Each successful call creates a new announcement and triggers notifications; there is no idempotency or deduplication. Content is trimmed and must not be blank or contain NUL characters.',
        tags: ['Announcements'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            type: 'object',
            required: ['title', 'body'],
            additionalProperties: false,
            properties: [
                new OA\Property(property: 'title', type: 'string', minLength: 1, maxLength: 150, description: 'Title; length is checked after trimming.', example: 'Maintenance notice'),
                new OA\Property(property: 'body', type: 'string', minLength: 1, maxLength: 2000, description: 'Plain text; length is checked after trimming. Internal whitespace and newlines are preserved.', example: 'Scheduled maintenance tonight.'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'A new announcement has been published.', content: new OA\JsonContent(
                type: 'object',
                required: ['data'],
                properties: [new OA\Property(property: 'data', type: 'object', required: ['id', 'status', 'published_at'], properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 123),
                    new OA\Property(property: 'status', type: 'string', enum: ['published']),
                    new OA\Property(property: 'published_at', type: 'string', format: 'date-time', description: 'UTC publication time.', example: '2026-10-12T08:00:00Z'),
                ])],
            )),
            new OA\Response(response: 400, ref: '#/components/responses/BadRequest'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 415, ref: '#/components/responses/UnsupportedMediaType'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function create(): ResponseInterface
    {
        $this->response->setHeader('Cache-Control', 'no-store');
        $user = auth('tokens')->getAuthenticator()->getUser();
        auth('session');

        try {
            $result = service('announcementapipublication')->publish((int) $user->id, get_object_vars($this->request->getJSON(false, 512, JSON_THROW_ON_ERROR)));

            return $this->respondCreated($result);
        } catch (InvalidAnnouncementInput $exception) {
            return $this->fail($exception->fields, 422);
        } catch (Throwable $exception) {
            log_message('error', 'API announcement publication failed: {type}', ['type' => $exception::class]);

            return $this->failServerError('The request could not be completed.');
        }
    }
}
