<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FactoryResource;
use App\Services\FactoryContext;

class FactoryContextController extends Controller
{
    public function show(FactoryContext $context): FactoryResource
    {
        return new FactoryResource($context->get());
    }
}
