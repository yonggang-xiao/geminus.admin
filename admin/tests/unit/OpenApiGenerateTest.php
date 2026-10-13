<?php

use App\Commands\OpenApiGenerate;
use CodeIgniter\CLI\Commands;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\StreamFilterTrait;
use Config\Autoload;
use Psr\Log\NullLogger;

/**
 * @internal
 */
final class OpenApiGenerateTest extends CIUnitTestCase
{
    use StreamFilterTrait;

    public function testGeneratesRegisteredModuleContract(): void
    {
        $output = tempnam(sys_get_temp_dir(), 'openapi-');
        $this->assertNotFalse($output);

        try {
            $command = new OpenApiGenerate(new NullLogger(), new Commands());
            $this->assertSame(EXIT_SUCCESS, $command->run([$output]));
            $document = json_decode(file_get_contents($output), true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame('3.1.0', $document['openapi']);
            $this->assertSame('Geminus API', $document['info']['title']);
            $this->assertArrayHasKey('/api/v1/announcements', $document['paths']);
            $operation = $document['paths']['/api/v1/announcements']['post'];
            $this->assertSame('publishAnnouncement', $operation['operationId']);
            $this->assertSame([['bearerAuth' => []]], $operation['security']);
            $this->assertStringContainsString('announcements.manage', $operation['description']);
            $this->assertStringContainsString('no idempotency or deduplication', $operation['description']);
            $this->assertTrue($operation['requestBody']['required']);
            $input = $operation['requestBody']['content']['application/json']['schema'];
            $this->assertSame(['title', 'body'], $input['required']);
            $this->assertFalse($input['additionalProperties']);
            $this->assertSame('string', $input['properties']['title']['type']);
            $this->assertSame(150, $input['properties']['title']['maxLength']);
            $this->assertSame('string', $input['properties']['body']['type']);
            $this->assertSame(2000, $input['properties']['body']['maxLength']);
            $this->assertSame([201, 400, 401, 403, 415, 422, 429, 500], array_keys($operation['responses']));
            $data = $operation['responses'][201]['content']['application/json']['schema']['properties']['data'];
            $this->assertSame(['id', 'status', 'published_at'], $data['required']);
            $this->assertSame('integer', $data['properties']['id']['type']);
            $this->assertSame(['published'], $data['properties']['status']['enum']);
            $this->assertSame('date-time', $data['properties']['published_at']['format']);
            $this->assertArrayNotHasKey('body', $data['properties']);

            $components = $document['components'];
            $this->assertSame('http', $components['securitySchemes']['bearerAuth']['type']);
            $this->assertSame('bearer', $components['securitySchemes']['bearerAuth']['scheme']);
            $this->assertSame(['status', 'error', 'messages'], $components['schemas']['ApiError']['required']);
            $this->assertSame('string', $components['schemas']['ApiError']['properties']['messages']['additionalProperties']['type']);

            foreach ([400 => 'BadRequest', 401 => 'Unauthorized', 403 => 'Forbidden', 415 => 'UnsupportedMediaType', 422 => 'ValidationError', 429 => 'TooManyRequests', 500 => 'ServerError'] as $statusCode => $response) {
                $this->assertSame('#/components/responses/' . $response, $operation['responses'][$statusCode]['$ref']);
                $this->assertSame('#/components/schemas/ApiError', $components['responses'][$response]['content']['application/json']['schema']['$ref']);
            }
            $this->assertSame('integer', $components['responses']['TooManyRequests']['headers']['Retry-After']['schema']['type']);
        } finally {
            unlink($output);
        }
    }

    public function testRejectsMissingOutputDirectory(): void
    {
        $directory = sys_get_temp_dir() . '/openapi-' . bin2hex(random_bytes(8));
        $command   = new OpenApiGenerate(new NullLogger(), new Commands());
        $this->resetStreamFilterBuffer();

        $this->assertSame(EXIT_ERROR, $command->run([$directory . '/openapi.json']));
        $this->assertStringContainsString('Output directory must exist and be writable', $this->getStreamFilterBuffer());
        $this->assertDirectoryDoesNotExist($directory);
    }

    public function testRejectsIncompleteDocumentWithoutWriting(): void
    {
        $autoload   = config(Autoload::class);
        $namespaces = $autoload->psr4;
        $output     = sys_get_temp_dir() . '/openapi-' . bin2hex(random_bytes(8)) . '.json';
        $command    = new OpenApiGenerate(new NullLogger(), new Commands());
        $this->resetStreamFilterBuffer();

        try {
            foreach ([
                ['Modules\\Announcements' => ROOTPATH . 'modules/Announcements'],
                ['App' => APPPATH, 'Modules\\Announcements' => ROOTPATH . 'modules/Announcements'],
            ] as $incompleteNamespaces) {
                $autoload->psr4 = $incompleteNamespaces;
                $this->resetStreamFilterBuffer();
                $this->assertSame(EXIT_ERROR, $command->run([$output]));
                $this->assertStringContainsString('OpenAPI document validation failed', $this->getStreamFilterBuffer());
                $this->assertFileDoesNotExist($output);
            }
        } finally {
            $autoload->psr4 = $namespaces;
        }
    }
}
