<?php

namespace MagsLabs\LaravelStoredProc;

use Illuminate\Http\Client\Request;
use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

use Exception;
use Throwable;


/**
 * Class StoredProcedure
 *
 * A fluent interface for executing stored procedures in Laravel.
 * 
 * ### Example Usage:
 * ```php
 * $result = StoredProcedure::stored_procedure('my_procedure')
 *    ->stored_procedure_params([':id']) //optional if parameters exist
 *    ->stored_procedure_values([1]) //optional if parameters exist
 *    ->with_transaction() // optional to enable transactions
 *    ->execute() 
 *    ->stored_procedure_result();
 * ```
 *
 * ### Transaction Support
 * You may call `->with_transaction()` in the chain to wrap the stored procedure in a database transaction.
 * Laravel will automatically `commit()` or `rollBack()` the transaction based on success or failure.
 * 
 * ⚠️ Only use this if your stored procedure does **not** contain its own transaction logic
 * (e.g., it does **not** use `BEGIN TRANSACTION`, `COMMIT`, or `ROLLBACK` internally).
 *
 * @method static self stored_procedure(string $procedure) Set the stored procedure name (Required, must be called first).
 * @method static self stored_procedure_connection(string $connection) Set a specific database connection (Optional).
 * @method static self stored_procedure_params(array|Request|FormRequest $params) Set procedure parameters (Optional, required if the procedure has parameters).
 * @method static self stored_procedure_values(array $values) Set procedure values corresponding to parameters (Optional, required if parameters exist).
 * @method static self with_transaction(bool $value = true) Optionally wrap the procedure in a Laravel-managed transaction.
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

    protected bool $use_transaction = false;
    private bool $is_sp_name_initialized = false;
    private bool $is_sp_params_initialized = false;
    private bool $is_sp_values_initialized = false;
    private bool $is_execute_called = false;

    protected function logger()
    {
        try {
            return Log::channel('magslabs_laravel_stored_proc');
        } catch (\InvalidArgumentException $e) {
            return Log::build([
                'driver' => 'errorlog',
                'level' => 'debug',
            ]);
        }
    }

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
     * Set the stored procedure name. [Required]
     *
     * This method **must be called first** before executing the stored procedure.
     *
     * @param string $procedure The stored procedure name.
     * @return self Provides method chaining.
     */
    public function stored_procedure(string $procedure = ''): self
    {
        $this->query = $this->command . ' ' . $procedure;
        $this->is_sp_name_initialized = true;

        $this->logger()->debug("Stored procedure set", ['procedure' => $procedure]);

        return $this;
    }

    /**
     * Set the database connection. [Optional]
     *
     * If not specified, the default database connection from `.env` (`DB_CONNECTION`) is used.
     *
     * @param string $connection The connection name (e.g., 'mysql', 'pgsql', 'sqlsrv').
     * @return self Provides method chaining.
     */
    public function stored_procedure_connection(string $connection = ''): self
    {
        $this->connection = $connection;

        $this->logger()->debug("Database connection set", ['connection' => $connection]);

        return $this;
    }

    /**
     * Set the stored procedure parameters. [Optional]
     *
     * This method **must be called after** `stored_procedure()` if parameters are required.
     *
     * @param array|Request|FormRequest $params Procedure parameters as an array, Request, or FormRequest.
     * @return self Provides method chaining.
     * @throws Exception If `stored_procedure()` was not called first.
     */
    public function stored_procedure_params(array|Request|FormRequest $params = []): self
    {
        if (!$this->is_sp_name_initialized) {
            $this->logger()->error("stored_procedure_params() called before stored_procedure()");
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
            // $this->params = implode(', ', $params_keys_array);
            $this->params = empty($params_keys_array) ? null : implode(', ', $params_keys_array);
        } else {
            // Ensure it's always a valid string
            // $this->params = implode(', ', $params) ?? '';
            $this->params = empty($params) ? null : implode(', ', $params);
        }

        $this->is_sp_params_initialized = true;

        $this->logger()->debug("Stored procedure parameters set", ['params' => $this->params]);

        return $this;
    }

    /**
     * Set the values for the stored procedure parameters. [Optional]
     *
     * This method **must be called after** `stored_procedure_params()` if parameters exist.
     *
     * @param array $values The parameter values.
     * @return self Provides method chaining.
     * @throws Exception If `stored_procedure_params()` was not called first.
     */
    public function stored_procedure_values(array $values = []): self
    {
        if (!$this->is_sp_params_initialized) {
            $this->logger()->error("stored_procedure_values() called before stored_procedure_params()");
            throw new Exception('You must call stored_procedure_params() before stored_procedure_values().');
        }

        if (empty($this->params)) {
            $this->logger()->error("Values were set but no parameters exist");
            throw new Exception('Cannot call stored_procedure_values() if there are no parameters.');
        }

        $this->values = $values ?? [];
        $this->is_sp_values_initialized = true;

        $this->logger()->debug("Stored procedure values set", ['values' => $values]);
        return $this;
    }

    /**
     * Enable database transaction wrapping during stored procedure execution. [Optional]
     *
     * ⚠️ **Use this only if your stored procedure does NOT handle its own transactions.**
     *
     * Laravel will begin a transaction before executing the procedure and commit it after execution.
     * If the procedure throws an error, the transaction will be rolled back automatically.
     *
     * #### ✅ Recommended Use:
     * - When your stored procedure performs multiple DML operations (INSERT, UPDATE, DELETE) **but does not manage transactions internally.**
     * - When you want Laravel to handle rollback automatically on exceptions.
     *
     * #### ⚠️ Avoid When:
     * - The stored procedure already includes `BEGIN TRANSACTION`, `COMMIT`, or `ROLLBACK`.
     * - You're calling nested stored procedures that manage their own transactions.
     *
     * @param bool $value Whether to wrap the execution in a Laravel-managed transaction. Default is true.
     * @return self Provides method chaining.
     */
    public function with_transaction(bool $use_transaction = true): self
    {
        $this->use_transaction = $use_transaction;

        $this->logger()->debug("Transaction enabled", ['use_transaction' => $use_transaction]);

        return $this;
    }

    /**
     * Execute the stored procedure. [Required]
     *
     * This method **must be called last** in the method chain.
     *
     * @return self Provides method chaining.
     * @throws Exception If `stored_procedure()` was not called first.
     */
    public function execute(): self
    {
        if (!$this->is_sp_name_initialized) {
            $this->logger()->error("execute() called before stored_procedure()");
            throw new Exception('You must call stored_procedure() before execute().');
        }

        // If params are set, values must also be set.
        if ($this->is_sp_params_initialized && !$this->is_sp_values_initialized) {
            $this->logger()->error("stored_procedure_values() missing after stored_procedure_params()");
            throw new Exception('You must call stored_procedure_values() after stored_procedure_params().');
        }

        // $bindings = $this->command == 'CALL' ? ' (' . $this->params . ');' : ' ' . $this->params;

        // Construct the SQL query dynamically based on the database type
        $bindings = ($this->command === 'CALL')
            // ? ($this->params ? " (" . $this->params . ");" : "();")
            ? ($this->params ? " (" . $this->params . ");" : "")
            : ($this->params ? " " . $this->params : "");

        // Construct the final query
        $this->query = $this->query . $bindings;

        // Checks if a specific database connection is set, otherwise use the default connection
        $dbConnection = $this->connection === ''
            ? $this->db::connection()
            : $this->db::connection($this->connection);

        $this->logger()->info("Executing stored procedure", [
            'query' => $this->query,
            'values' => $this->values,
            'use_transaction' => $this->use_transaction,
            'connection' => $this->connection ?: 'default',
        ]);

        try {
            if ($this->use_transaction) {
                $dbConnection->beginTransaction();
            }

            $this->result = empty($this->values)
                ? $dbConnection->select($this->query)
                : $dbConnection->select($this->query, $this->values);

            if ($this->use_transaction) {
                $dbConnection->commit();
            }

            $this->logger()->info("Stored procedure executed successfully");
        } catch (Throwable $throwable) {
            if ($this->use_transaction) {
                $dbConnection->rollBack();
            }

            $this->logger()->error("Stored procedure execution failed", [
                'error' => $throwable->getMessage(),
                'exception' => get_class($throwable),
                'trace' => $throwable->getTraceAsString(),
                'query' => $this->query,
                'values' => $this->values,
            ]);

            throw $throwable;
        }

        $this->is_execute_called = true;
        return $this;
    }

    /**
     * Retrieve the result of the stored procedure. [Required]
     *
     * This method **must be called after** `execute()`.
     *
     * @return Collection|array The stored procedure result as a collection or an array.
     * @throws Exception If `execute()` was not called first.
     */
    public function stored_procedure_result()
    {
        if (!$this->is_execute_called) {
            $this->logger()->error("Attempted to retrieve stored procedure result before execution");
            throw new Exception('You must call execute() before stored_procedure_result().');
        }

        $this->logger()->debug("Returning stored procedure result", [
            'records_found' => count($this->result ?? [])
        ]);

        // Return results as a Laravel Collection or an empty Collection if no records were found
        return collect($this->result)->count() > 0
            ? Collection::make($this->result)
            : Collection::make([]);
    }
}
