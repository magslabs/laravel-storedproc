<?php

namespace MagsLabs\LaravelStoredProc\Contracts;

use MagsLabs\LaravelStoredProc\Data\ProcedureParameter;

interface StoredProcedureIntrospector
{
    public function exists(string $name, ?string $schema = null): bool;

    /**
     * @return array<int, ProcedureParameter>
     */
    public function parameters(string $name, ?string $schema = null): array;
}
