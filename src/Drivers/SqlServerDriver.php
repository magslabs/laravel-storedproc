<?php

namespace MagsLabs\LaravelStoredProc\Drivers;

use Illuminate\Database\Connection;
use MagsLabs\LaravelStoredProc\Contracts\StoredProcedureDriver;
use MagsLabs\LaravelStoredProc\Data\ExecutionResult;
use PDO;

class SqlServerDriver implements StoredProcedureDriver
{
    public function name(): string
    {
        return 'sqlsrv';
    }

    public function command(): string
    {
        return 'EXEC';
    }

    public function defaultOutputType(): string
    {
        return 'BIT';
    }

    public function buildCall(string $procedure, ?string $params): string
    {
        $bindings = $params ? ' '.$params : '';

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
        $pdo = $connection->getPdo();

        if (! empty($output_params)) {
            $declare_stmts = [];
            $exec_call = $sp_call;
            $select_stmts = [];

            foreach ($output_params as $param => $type) {
                $clean_param = $this->cleanOutputParamName($param);
                $declare_stmts[] = "DECLARE $clean_param $type;";
                $select_stmts[] = "$clean_param AS ".ltrim($clean_param, '@');

                $exec_call = preg_replace(
                    '/('.preg_quote($clean_param, '/').')(?!\s+OUTPUT\b)/i',
                    '$1 OUTPUT',
                    $exec_call,
                    1
                );
            }

            $full_query = implode("\n", $declare_stmts)."\n"
                .$exec_call."\n"
                .'SELECT '.implode(', ', $select_stmts).';';

            $stmt = $pdo->prepare($full_query);
            $stmt->execute($values);

            while ($stmt->columnCount() === 0 && $stmt->nextRowset()) {
                // advance to first result set with columns
            }

            return new ExecutionResult([], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        $result = empty($values)
            ? $connection->select($sp_call)
            : $connection->select($sp_call, $values);

        return new ExecutionResult($result, []);
    }

    private function cleanOutputParamName(string $param): string
    {
        return trim(str_replace('OUTPUT', '', $param));
    }
}
