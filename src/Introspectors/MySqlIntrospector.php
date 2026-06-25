<?php

namespace MagsLabs\LaravelStoredProc\Introspectors;

use Illuminate\Database\Connection;
use MagsLabs\LaravelStoredProc\Contracts\StoredProcedureIntrospector;
use MagsLabs\LaravelStoredProc\Data\ProcedureParameter;

class MySqlIntrospector implements StoredProcedureIntrospector
{
    public function __construct(
        protected Connection $connection,
    ) {}

    public function exists(string $name, ?string $schema = null): bool
    {
        $schema = $schema ?? $this->connection->getDatabaseName();

        $result = $this->connection->selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = ? AND ROUTINE_NAME = ? AND ROUTINE_TYPE = ?',
            [$schema, $this->bareName($name), 'PROCEDURE']
        );

        return (int) ($result->total ?? 0) > 0;
    }

    public function parameters(string $name, ?string $schema = null): array
    {
        $schema = $schema ?? $this->connection->getDatabaseName();

        $rows = $this->connection->select(
            'SELECT PARAMETER_NAME, ORDINAL_POSITION, PARAMETER_MODE, DTD_IDENTIFIER
             FROM information_schema.PARAMETERS
             WHERE SPECIFIC_SCHEMA = ? AND SPECIFIC_NAME = ?
             ORDER BY ORDINAL_POSITION',
            [$schema, $this->bareName($name)]
        );

        $parameters = [];
        foreach ($rows as $row) {
            if ($row->PARAMETER_NAME === null) {
                continue;
            }

            $parameters[] = new ProcedureParameter(
                name: $row->PARAMETER_NAME,
                ordinal: (int) $row->ORDINAL_POSITION,
                direction: strtoupper($row->PARAMETER_MODE ?? 'IN'),
                data_type: $row->DTD_IDENTIFIER ?? null,
            );
        }

        return $parameters;
    }

    private function bareName(string $name): string
    {
        if (str_contains($name, '.')) {
            return substr($name, strrpos($name, '.') + 1);
        }

        return $name;
    }
}
