<?php

namespace MagsLabs\LaravelStoredProc\Validation;

use MagsLabs\LaravelStoredProc\Contracts\StoredProcedureIntrospector;
use MagsLabs\LaravelStoredProc\Data\ProcedureParameter;
use MagsLabs\LaravelStoredProc\Exceptions\ParameterMismatchException;
use MagsLabs\LaravelStoredProc\Exceptions\StoredProcedureNotFoundException;

class StoredProcedureValidator
{
    /**
     * @param  array<string, string>  $output_param_definitions
     */
    public function validate(
        StoredProcedureIntrospector $introspector,
        string $qualified_procedure_name,
        ?string $params_string,
        array $input_values,
        array $output_param_definitions,
    ): void {
        if (! $introspector->exists($qualified_procedure_name)) {
            throw new StoredProcedureNotFoundException(
                "Stored procedure [{$qualified_procedure_name}] was not found on the database."
            );
        }

        $db_parameters = $introspector->parameters($qualified_procedure_name);

        if ($db_parameters === []) {
            return;
        }

        $caller = $this->parseCallerParameters($params_string, $output_param_definitions);
        $expected_inputs = array_values(array_filter($db_parameters, fn (ProcedureParameter $p) => $p->isInput()));
        $expected_outputs = array_values(array_filter($db_parameters, fn (ProcedureParameter $p) => $p->isOutput()));

        if (count($caller['inputs']) !== count($expected_inputs)) {
            throw new ParameterMismatchException($this->buildCountMessage(
                $qualified_procedure_name,
                'input',
                count($expected_inputs),
                count($caller['inputs']),
                $expected_inputs,
                $caller['inputs'],
            ));
        }

        if (count($caller['outputs']) !== count($expected_outputs)) {
            throw new ParameterMismatchException($this->buildCountMessage(
                $qualified_procedure_name,
                'output',
                count($expected_outputs),
                count($caller['outputs']),
                $expected_outputs,
                $caller['outputs'],
            ));
        }

        if (count($expected_inputs) > 0 && count($input_values) !== count($expected_inputs)) {
            throw new ParameterMismatchException(
                "Stored procedure [{$qualified_procedure_name}] expects ".count($expected_inputs)
                .' input value(s) via stored_procedure_values(), but '.count($input_values).' were provided.'
            );
        }
    }

    /**
     * @param  array<string, string>  $output_param_definitions
     * @return array{inputs: array<int, string>, outputs: array<int, string>}
     */
    private function parseCallerParameters(?string $params_string, array $output_param_definitions): array
    {
        if ($params_string === null || $params_string === '') {
            return ['inputs' => [], 'outputs' => []];
        }

        $inputs = [];
        $outputs = [];
        $output_names = array_map(
            fn (string $name) => $this->normalizeParamName($name),
            array_keys($output_param_definitions)
        );

        foreach (array_map('trim', explode(',', $params_string)) as $token) {
            if ($token === '') {
                continue;
            }

            $is_output_keyword = (bool) preg_match('/\b(OUTPUT|OUT)\b/i', $token);
            $normalized = $this->normalizeParamName($token);

            if ($is_output_keyword || in_array($normalized, $output_names, true)) {
                $outputs[] = $normalized;
            } else {
                $inputs[] = $normalized;
            }
        }

        return ['inputs' => $inputs, 'outputs' => $outputs];
    }

    private function normalizeParamName(string $token): string
    {
        $token = preg_replace('/\b(OUTPUT|OUT)\b/i', '', $token) ?? $token;

        return strtolower(trim($token));
    }

    /**
     * @param  array<int, ProcedureParameter>  $expected
     * @param  array<int, string>  $provided
     */
    private function buildCountMessage(
        string $procedure_name,
        string $kind,
        int $expected_count,
        int $provided_count,
        array $expected,
        array $provided,
    ): string {
        $lines = [
            "Stored procedure [{$procedure_name}] {$kind} parameter mismatch: expected {$expected_count}, got {$provided_count}.",
        ];

        if ($expected !== []) {
            $lines[] = 'Database expects:';
            foreach ($expected as $parameter) {
                $type = $parameter->data_type ? " ({$parameter->data_type})" : '';
                $lines[] = "  - {$parameter->name}{$type} [{$parameter->direction}]";
            }
        }

        if ($provided !== []) {
            $lines[] = 'You provided:';
            foreach ($provided as $name) {
                $lines[] = "  - {$name}";
            }
        }

        return implode("\n", $lines);
    }
}
