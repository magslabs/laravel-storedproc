<?php

namespace MagsLabs\LaravelStoredProc;

use Illuminate\Http\Client\Request;
use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

use Exception;

/**
 * Class StoredProcedure
 *
 * A fluent interface for executing stored procedures in Laravel.
 * 
 * ### Example Usage:
 * ```php
 * $result = StoredProcedure::stored_procedure('my_procedure')
 *      ->stored_procedure_params(['id' => 1])
 *      ->stored_procedure_values([1])
 *      ->execute()
 *      ->stored_procedure_result();
 * ```
 *
 * @method static self stored_procedure(string $procedure) Set the stored procedure name (Required, must be called first).
 * @method static self stored_procedure_connection(string $connection) Set a specific database connection (Optional).
 * @method static self stored_procedure_params(array|Request|FormRequest $params) Set procedure parameters (Optional, required if the procedure has parameters).
 * @method static self stored_procedure_values(array $values) Set procedure values corresponding to parameters (Optional, required if parameters exist).
 * @method static self execute() Execute the stored procedure (Required, must be called last).
 * @method static Collection|array stored_procedure_result() Retrieve the stored procedure result (Required).
 */
class StoredProcedure
{
    protected $db;
    protected $db_driver;
    protected $command;
    protected $query;
    protected $params;
    protected $values;
    protected $connection;
    protected $result;

    private bool $is_sp_name_initialized = false;
    private bool $is_sp_params_initialized = false;
    private bool $is_sp_values_initialized = false;
    private bool $is_execute_called = false;

    public function __construct()
    {
        $this->db = DB::class;
        $this->db_driver = $this->db::getConfig("driver");

        // Determine the correct stored procedure execution command based on the database driver
        switch ($this->db_driver) {
            case 'mysql':
                $this->command = 'CALL'; // MySQL stored procedures use CALL
                break;
            case 'sqlsrv':
                $this->command = 'EXEC'; // SQL Server stored procedures use EXEC
                break;
            default:
                $this->command = 'CALL'; // Default to MySQL behavior
                break;
        }
    }

    /**
     * [Required]
     * Set the stored procedure name.
     *
     * This method **must be called first** before executing the stored procedure.
     *
     * @param string $procedure The stored procedure name.
     * @return self Provides method chaining.
     */
    public function stored_procedure(string $procedure = '')
    {
        $this->query = $this->command . ' ' . $procedure;
        $this->is_sp_name_initialized = true;
        return $this;
    }


    /**
     * [Optional]
     * Set the database connection.
     *
     * If not specified, the default database connection from `.env` (`DB_CONNECTION`) is used.
     *
     * @param string $connection The connection name (e.g., 'mysql', 'pgsql', 'sqlsrv').
     * @return self Provides method chaining.
     */
    public function stored_procedure_connection(string $connection = '')
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Set the stored procedure parameters.
     *
     * This method **must be called after** `stored_procedure()` if parameters are required.
     *
     * @param array|Request|FormRequest $params Procedure parameters as an array, Request, or FormRequest.
     * @return self Provides method chaining.
     * @throws Exception If `stored_procedure()` was not called first.
     */
    public function stored_procedure_params(array|Request|FormRequest $params = [])
    {
        if (!$this->is_sp_name_initialized) {
            throw new Exception('You must call stored_procedure() before stored_procedure_params().');
        }

        if ($params instanceof Request || $params instanceof FormRequest) {
            // Remove CSRF token if it exists in the request
            unset($params['_token']);

            // Extract parameter names and format them as SQL placeholders (e.g., ":id")
            $params_keys = array_keys($params->toArray());

            // declare an array to store the key names
            $params_keys_array = [];

            // populate the params keys array with the key names
            foreach ($params_keys as $key) {
                $i = ':' . $key;
                $params_keys_array[] = $i;
            }

            // Convert the array into a comma-separated string
            $this->params = implode(', ', $params_keys_array);
        } else {
            // Ensure it's always a valid string
            $this->params = implode(', ', $params) ?? '';
        }

        $this->is_sp_params_initialized = true;
        return $this;
    }

    /**
     * Set the values for the stored procedure parameters.
     *
     * This method **must be called after** `stored_procedure_params()` if parameters exist.
     *
     * @param array $values The parameter values.
     * @return self Provides method chaining.
     * @throws Exception If `stored_procedure_params()` was not called first.
     */
    public function stored_procedure_values(array $values = [])
    {
        if (!$this->is_sp_params_initialized) {
            throw new Exception('You must call stored_procedure_params() before stored_procedure_values().');
        }

        $this->values = $values ?? [];

        $this->is_sp_values_initialized = true;
        return $this;
    }


    /**
     * [Required]
     * Execute the stored procedure.
     *
     * This method **must be called last** in the method chain.
     *
     * @return self Provides method chaining.
     * @throws Exception If `stored_procedure()` was not called first.
     */
    public function execute()
    {
        if (!$this->is_sp_name_initialized) {
            throw new Exception('You must call stored_procedure() before execute().');
        }

        // If params are set, values must also be set.
        if ($this->is_sp_params_initialized && !$this->is_sp_values_initialized) {
            throw new Exception('You must call stored_procedure_values() after stored_procedure_params().');
        }

        // $bindings = $this->command == 'CALL' ? ' (' . $this->params . ');' : ' ' . $this->params;

        // Construct the SQL query dynamically based on the database type
        $bindings = ($this->command === 'CALL')
            ? ((!empty($this->params)) ? " (" . $this->params . ");" : "();")
            : ((!empty($this->params)) ? " " . $this->params : "");

        // Construct the final query
        $this->query = $this->query . $bindings;

        // if ($this->connection == '') {
        //     if ($this->values == []) {
        //         $this->result = $this->db::select($this->query);
        //     } else {
        //         $this->result = $this->db::select($this->query, $this->values);
        //     }
        // } else {
        //     if ($this->values == []) {
        //         $this->result = $this->db::connection($this->connection)->select($this->query);
        //     } else {
        //         $this->result = $this->db::connection($this->connection)->select($this->query, $this->values);
        //     }
        // }

        // Execute the stored procedure with or without a specific database connection
        if ($this->connection == '') {
            $this->result = empty($this->values)
                ? $this->db::select($this->query)
                : $this->db::select($this->query, $this->values);
        } else {
            $this->result = empty($this->values)
                ? $this->db::connection($this->connection)->select($this->query)
                : $this->db::connection($this->connection)->select($this->query, $this->values);
        }

        $this->is_execute_called = true;
        return $this;
    }

    /**
     * [Required]
     * Retrieve the result of the stored procedure.
     *
     * This method **must be called after** `execute()`.
     *
     * @return Collection|array The stored procedure result as a collection or an array.
     * @throws Exception If `execute()` was not called first.
     */
    public function stored_procedure_result()
    {
        if (!$this->is_execute_called) {
            throw new Exception('You must call execute() before stored_procedure_result().');
        }

        // $record_count = collect($this->result)->count();
        // if ($record_count > 0) {
        //     return Collection::make($this->result);
        // } else {
        //     return Collection::make([]);
        // }

        // Return results as a Laravel Collection or an empty Collection if no records were found
        return collect($this->result)->count() > 0
            ? Collection::make($this->result)
            : Collection::make([]);
    }
}
