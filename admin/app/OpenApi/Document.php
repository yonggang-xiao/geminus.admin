<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(version: '1.0.0', title: 'Geminus API')]
#[OA\Server(url: '/', description: 'Current deployment')]
class Document
{
}
