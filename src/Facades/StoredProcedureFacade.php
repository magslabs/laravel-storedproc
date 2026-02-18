<?php

namespace MagsLabs\LaravelStoredProc\Facades;

use Illuminate\Support\Facades\Facade;
use MagsLabs\LaravelStoredProc\StoredProcedure;

/**
 * @method static StoredProcedure stored_procedure(string $procedure = '')
 * @method static StoredProcedure stored_procedure_connection(string $connection = '')
 * @method static StoredProcedure stored_procedure_params(array|\Illuminate\Http\Request $params = [])
 * @method static StoredProcedure stored_procedure_values(array $values = [])
 * @method static StoredProcedure stored_procedure_output_params(array $output_params = [])
 * @method static StoredProcedure with_transaction(bool $value = true)
 * @method static StoredProcedure execute()
 * @method static \Illuminate\Support\Collection|object stored_procedure_result()
 * @method static mixed stored_procedure_output_results()
 * @method static StoredProcedure reset()
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
