<?php

namespace MagsLabs\LaravelStoredProc\Contracts;

use Illuminate\Database\Connection;
use MagsLabs\LaravelStoredProc\Data\ExecutionResult;

interface StoredProcedureDriver
{
    public function name(): string;

    public function command(): string;

    public function defaultOutputType(): string;

    public function buildCall(string $procedure, ?string $params): string;

    /**
     * @param  array<string, string>  $output_params
     */
    public function execute(
        Connection $connection,
        string $procedure,
        ?string $params,
        array $values,
        array $output_params,
    ): ExecutionResult;
}
