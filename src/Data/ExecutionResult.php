<?php

namespace MagsLabs\LaravelStoredProc\Data;

final class ExecutionResult
{
    /**
     * @param  array<int, array<string, mixed>>  $result
     * @param  array<int, array<string, mixed>>  $output_results
     */
    public function __construct(
        public readonly array $result,
        public readonly array $output_results,
    ) {}
}
