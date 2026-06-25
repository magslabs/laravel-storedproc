<?php

namespace MagsLabs\LaravelStoredProc\Introspectors;

use Illuminate\Database\Connection;
use MagsLabs\LaravelStoredProc\Contracts\StoredProcedureIntrospector;
use MagsLabs\LaravelStoredProc\Data\ProcedureParameter;

class SqlServerIntrospector implements StoredProcedureIntrospector
{
    public function __construct(
        protected Connection $connection,
    ) {}

    public function exists(string $name, ?string $schema = null): bool
    {
        [$schema, $procedure] = $this->parseName($name, $schema);
        $qualified = $schema.'.'.$procedure;

        $result = $this->connection->selectOne(
            'SELECT CASE
                WHEN OBJECT_ID(?, ?) IS NOT NULL THEN 1
                WHEN EXISTS (
                    SELECT 1
                    FROM sys.synonyms syn
                    INNER JOIN sys.schemas s ON syn.schema_id = s.schema_id
                    WHERE syn.name = ? AND s.name = ?
                ) THEN 1
                ELSE 0
            END AS found',
            [$qualified, 'P', $procedure, $schema]
        );

        return (int) ($result->found ?? 0) === 1;
    }

    public function parameters(string $name, ?string $schema = null): array
    {
        [$schema, $procedure] = $this->parseName($name, $schema);
        $qualified = $schema.'.'.$procedure;

        $rows = $this->connection->select(
            'SELECT p.name AS parameter_name,
                    p.parameter_id AS ordinal_position,
                    CASE WHEN p.is_output = 1 THEN ? ELSE ? END AS parameter_mode,
                    t.name AS data_type
             FROM sys.parameters p
             INNER JOIN sys.types t ON p.user_type_id = t.user_type_id
             WHERE p.object_id = OBJECT_ID(?)
             ORDER BY p.parameter_id',
            ['OUT', 'IN', $qualified]
        );

        $parameters = [];
        foreach ($rows as $row) {
            if ((int) $row->ordinal_position === 0) {
                continue;
            }

            $parameters[] = new ProcedureParameter(
                name: $row->parameter_name,
                ordinal: (int) $row->ordinal_position,
                direction: strtoupper($row->parameter_mode ?? 'IN'),
                data_type: $row->data_type ?? null,
            );
        }

        return $parameters;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseName(string $name, ?string $schema): array
    {
        if (str_contains($name, '.')) {
            [$schema_part, $procedure] = explode('.', $name, 2);

            return [$schema_part, $procedure];
        }

        return [$schema ?? 'dbo', $name];
    }
}
