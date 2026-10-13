<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Autoload;
use OpenApi\Generator;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use RuntimeException;
use Stringable;
use Throwable;

class OpenApiGenerate extends BaseCommand
{
    protected $group       = 'Documentation';
    protected $name        = 'openapi:generate';
    protected $description = 'Generate the OpenAPI JSON document for registered application modules.';
    protected $usage       = 'openapi:generate [output]';
    protected $arguments   = ['output' => 'Output JSON file; defaults to writable/openapi.json.'];

    public function run(array $params): int
    {
        if (! class_exists(Generator::class)) {
            CLI::error('swagger-php is required. Install Composer development dependencies.');

            return EXIT_ERROR;
        }

        try {
            $sources = [];

            foreach (config(Autoload::class)->psr4 as $directories) {
                foreach ((array) $directories as $directory) {
                    foreach (['OpenApi', 'Controllers'] as $subdirectory) {
                        $source = rtrim($directory, '/\\') . '/' . $subdirectory;
                        if (is_dir($source)) {
                            $sources[] = $source;
                        }
                    }
                }
            }

            $logger = new class () extends AbstractLogger {
                public function log($level, string|Stringable $message, array $context = []): void
                {
                    if (in_array($level, [LogLevel::WARNING, LogLevel::ERROR, LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY], true)) {
                        throw new RuntimeException('OpenAPI document validation failed: ' . $message);
                    }
                }
            };
            $document = (new Generator($logger))->setVersion('3.1.0')->generate($sources);
            if ($document === null) {
                throw new RuntimeException('OpenAPI document validation failed.');
            }

            $output = $params[0] ?? WRITEPATH . 'openapi.json';
            if (! is_dir(dirname($output)) || ! is_writable(dirname($output))) {
                throw new RuntimeException('Output directory must exist and be writable: ' . dirname($output));
            }
            if (file_put_contents($output, $document->toJson()) === false) {
                throw new RuntimeException('Could not write the OpenAPI document: ' . $output);
            }

            CLI::write('OpenAPI document generated: ' . $output, 'green');

            return EXIT_SUCCESS;
        } catch (Throwable $exception) {
            CLI::error($exception->getMessage());

            return EXIT_ERROR;
        }
    }
}
