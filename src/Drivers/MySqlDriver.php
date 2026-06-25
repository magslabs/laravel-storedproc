<?php

namespace MagsLabs\LaravelStoredProc\Drivers;

use Illuminate\Database\Connection;
use MagsLabs\LaravelStoredProc\Contracts\StoredProcedureDriver;
use MagsLabs\LaravelStoredProc\Data\ExecutionResult;
use PDO;

class MySqlDriver implements StoredProcedureDriver
{
    public function name(): string
    {
        return 'mysql';
    }

    public function command(): string
    {
        return 'CALL';
    }

    public function defaultOutputType(): string
    {
        return 'BIT';
    }

    public function buildCall(string $procedure, ?string $params): string
    {
        $bindings = $params ? ' ('.$params.');' : '();';

        return $this->command().' '.$procedure.$bindings;
    }

    public function execute(
        Connection $connection,
        string $procedure,
        ?string $params,
        array $values,
        array $output_params,
    ): ExecutionResult {
        $sp_call = $this->buildCall($procedure, $params);

        if (! empty($output_params)) {
            foreach ($output_params as $param => $type) {
                $clean_param = $this->cleanOutputParamName($param);
                $connection->statement("SET @$clean_param = NULL");
            }

            $clean_call = $sp_call;
            foreach ($output_params as $param => $type) {
                $clean_param = $this->cleanOutputParamName($param);
                $clean_call = preg_replace(
                    '/('.preg_quote($clean_param, '/').')\s+OUT\b/i',
                    '$1',
                    $clean_call
                );
            }

            $connection->select($clean_call, $values);

            $select_stmts = [];
            foreach ($output_params as $param => $type) {
                $clean_param = $this->cleanOutputParamName($param);
                $select_stmts[] = "@$clean_param AS ".ltrim($clean_param, '@');
            }

            $output_results = $connection->select('SELECT '.implode(', ', $select_stmts));

            return new ExecutionResult([], $output_results);
        }

        $result = empty($values)
            ? $connection->select($sp_call)
            : $connection->select($sp_call, $values);

        return new ExecutionResult($result, []);
    }

    private function cleanOutputParamName(string $param): string
    {
        return trim(str_replace(['OUT', 'OUTPUT'], '', $param));
    }
}
