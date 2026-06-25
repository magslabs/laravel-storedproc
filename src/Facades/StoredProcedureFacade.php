<?php

namespace MagsLabs\LaravelStoredProc\Facades;

use Illuminate\Support\Facades\Facade;
use MagsLabs\LaravelStoredProc\StoredProcedure;

/**
 * @method static StoredProcedure stored_procedure(string $procedure = '')
 * @method static StoredProcedure stored_procedure_connection(string $connection = '')
 * @method static StoredProcedure stored_procedure_schema(?string $schema = null)
 * @method static StoredProcedure stored_procedure_params(array|\Illuminate\Http\Request $params = [])
 * @method static StoredProcedure stored_procedure_values(array $values = [])
 * @method static StoredProcedure stored_procedure_output_params(array $output_params = [])
 * @method static StoredProcedure with_transaction(bool $value = true)
 * @method static StoredProcedure validate()
 * @method static StoredProcedure assertExists()
 * @method static bool exists()
 * @method static StoredProcedure execute()
 * @method static \Illuminate\Support\Collection|object stored_procedure_result()
 * @method static mixed stored_procedure_output_results()
 * @method static StoredProcedure reset()
 * @method static StoredProcedure procedure(string $procedure = '')
 * @method static StoredProcedure connection(string $connection = '')
 * @method static StoredProcedure params(array|\Illuminate\Http\Request $params = [])
 * @method static StoredProcedure values(array $values = [])
 * @method static StoredProcedure outputs(array $output_params = [])
 * @method static StoredProcedure run()
 * @method static mixed result()
 * @method static mixed outputResults()
 *
 * @see \MagsLabs\LaravelStoredProc\StoredProcedure
 */
class StoredProcedureFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return StoredProcedure::class;
    }
}
