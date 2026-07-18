<?php

namespace App\Domain\ApiClients;

use App\Models\ApiClient;

final readonly class ApiClientCredentials
{
    public function __construct(public ApiClient $client, public string $secret) {}
}
