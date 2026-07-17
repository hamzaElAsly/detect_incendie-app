<?php

namespace App\Services;

use App\Models\Factory;
use LogicException;

class FactoryContext
{
    private ?Factory $factory = null;

    public function set(Factory $factory): void
    {
        $this->factory = $factory;
    }

    public function get(): Factory
    {
        return $this->factory ?? throw new LogicException('Factory context has not been resolved.');
    }

    public function id(): string
    {
        return $this->get()->getKey();
    }

    public function clear(): void
    {
        $this->factory = null;
    }
}
