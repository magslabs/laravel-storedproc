<?php

namespace MagsLabs\LaravelStoredProc\Drivers;

use Illuminate\Database\Connection;
use MagsLabs\LaravelStoredProc\Contracts\StoredProcedureDriver;
use MagsLabs\LaravelStoredProc\Data\ExecutionResult;
use MagsLabs\LaravelStoredProc\Exceptions\StoredProcedureException;
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

        if (! empty($output_params)) {
            $pdo = $connection->getPdo();
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

            if ($stmt->errorCode() !== '00000' && $stmt->errorCode() !== '01000') {
                $error = $stmt->errorInfo();

                throw new StoredProcedureException(
                    'SQL Server stored procedure execution failed: '.($error[2] ?? 'Unknown error')
                );
            }

            while ($stmt->columnCount() === 0 && $stmt->nextRowset()) {
                // advance to first result set with columns
            }

            $outputs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($outputs === false) {
                throw new StoredProcedureException(
                    'SQL Server stored procedure output fetch failed.'
                );
            }

            return new ExecutionResult([], $outputs);
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
