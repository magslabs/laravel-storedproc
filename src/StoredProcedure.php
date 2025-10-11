<?php

namespace MagsLabs\LaravelStoredProc;

use Exception;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
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
 * Only use this if your stored procedure does **not** contain its own transaction logic
 * (e.g., it does **not** use `BEGIN TRANSACTION`, `COMMIT`, or `ROLLBACK` internally).
 *
 * @method static self stored_procedure(string $procedure) Set the stored procedure name (Required, must be called first).
 * @method static self stored_procedure_connection(string $connection) Set a specific database connection (Optional).
 * @method static self stored_procedure_params(array|Request|FormRequest $params) Set procedure parameters including OUTPUT params with OUTPUT keyword (Optional, required if the procedure has parameters).
 * @method static self stored_procedure_values(array $values) Set procedure values for input parameters only (Optional, required if input parameters exist).
 * @method static self stored_procedure_output_params(array $output_params) Define SQL types for OUTPUT/OUT parameters (Optional, SQL Server/MySQL).
 * @method static self with_transaction(bool $value = true) Optionally wrap the procedure in a Laravel-managed transaction.
 * @method static self execute() Execute the stored procedure (Required, must be called last).
 * @method static mixed stored_procedure_result() Retrieve the stored procedure result as Collection or object with result/output properties (Required).
 * @method static mixed stored_procedure_output_results() Retrieve only OUTPUT/OUT parameter results (Optional, SQL Server/MySQL).
 * @method static self reset() Manually reset the instance state (Optional).
 */
class StoredProcedure
{
    /**
     * @var string The database facade class
     */
    protected $db;

    /**
     * @var string The database driver (mysql, sqlsrv, etc.)
     */
    protected $db_driver;

    /**
     * @var string The SQL command to execute (CALL for MySQL, EXEC for SQL Server)
     */
    protected $command;

    /**
     * @var string|null The constructed SQL query
     */
    protected $query;

    /**
     * @var string|null The parameter string for the stored procedure
     */
    protected $params;

    /**
     * @var array The parameter values to bind
     */
    protected $values;

    /**
     * @var string|null The database connection name
     */
    protected $connection;

    /**
     * @var array The stored procedure execution result
     */
    protected $result;

    /**
     * @var array OUTPUT parameter definitions with SQL types
     */
    protected $output_params = [];

    /**
     * @var array OUTPUT parameter results after execution
     */
    protected $output_results = [];

    /**
     * @var bool Whether to wrap execution in a transaction
     */
    protected bool $use_transaction = false;

    /**
     * @var bool Whether the stored procedure name has been initialized
     */
    private bool $is_sp_name_initialized = false;

    /**
     * @var bool Whether the stored procedure parameters have been initialized
     */
    private bool $is_sp_params_initialized = false;

    /**
     * @var bool Whether the stored procedure values have been initialized
     */
    private bool $is_sp_values_initialized = false;

    /**
     * @var bool Whether the execute method has been called
     */
    private bool $is_execute_called = false;

    /**
     * Get the logger instance for this package.
     *
     * Attempts to use the dedicated log channel, falls back to error log if not configured.
     *
     * @return \Psr\Log\LoggerInterface The logger instance.
     *
     * @since 1.0.0
     */
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

    /**
     * Initialize the StoredProcedure instance.
     *
     * Automatically detects the database driver and sets the appropriate command:
     * - MySQL: Uses 'CALL' command
     * - SQL Server: Uses 'EXEC' command
     * - Default: Falls back to 'CALL' (MySQL behavior)
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->db = DB::class;
        $this->db_driver = $this->db::getConfig('driver');

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
     * @param  string  $procedure  The stored procedure name.
     * @return self Provides method chaining.
     *
     * @api
     *
     * @since 1.0.0
     */
    public function stored_procedure(string $procedure = ''): self
    {
        $this->query = $this->command.' '.$procedure;
        $this->is_sp_name_initialized = true;

        $this->logger()->debug('Stored procedure set', ['procedure' => $procedure]);

        return $this;
    }

    /**
     * Set the database connection. [Optional]
     *
     * If not specified, the default database connection from `.env` (`DB_CONNECTION`) is used.
     *
     * @param  string  $connection  The connection name (e.g., 'mysql', 'pgsql', 'sqlsrv').
     * @return self Provides method chaining.
     *
     * @api
     *
     * @since 1.0.0
     */
    public function stored_procedure_connection(string $connection = ''): self
    {
        $this->connection = $connection;

        $this->logger()->debug('Database connection set', ['connection' => $connection]);

        return $this;
    }

    /**
     * Set the stored procedure parameters. [Optional]
     *
     * This method **must be called after** `stored_procedure()` if parameters are required.
     *
     * For OUTPUT/OUT parameters (SQL Server/MySQL), include them with the appropriate keyword:
     * ->stored_procedure_params([':input_param', '@output_param OUTPUT']) // SQL Server
     * ->stored_procedure_params([':input_param', '@output_param OUT'])     // MySQL
     *
     * @param  array|Request|FormRequest  $params  Procedure parameters as an array, Request, or FormRequest.
     *                                             For OUTPUT parameters, include with 'OUTPUT' (SQL Server) or 'OUT' (MySQL) keyword.
     * @return self Provides method chaining.
     *
     * @throws Exception If `stored_procedure()` was not called first.
     *
     * @example
     * // Basic parameters
     * ->stored_procedure_params([':id', ':name'])
     *
     * // With OUTPUT parameters (SQL Server)
     * ->stored_procedure_params([':user_id', '@result OUTPUT', '@message OUTPUT'])
     *
     * // With OUT parameters (MySQL)
     * ->stored_procedure_params([':user_id', '@result OUT', '@message OUT'])
     *
     * // From Request object
     * ->stored_procedure_params($request)
     *
     * @api
     *
     * @since 1.0.0
     */
    public function stored_procedure_params(array|Request|FormRequest $params = []): self
    {
        if (! $this->is_sp_name_initialized) {
            $this->logger()->error('stored_procedure_params() called before stored_procedure()');
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
                $i = ':'.$key;
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

        $this->logger()->debug('Stored procedure parameters set', ['params' => $this->params]);

        return $this;
    }

    /**
     * Set the values for the stored procedure parameters. [Optional]
     *
     * This method **must be called after** `stored_procedure_params()` if parameters exist.
     *
     * @param  array  $values  The parameter values.
     * @return self Provides method chaining.
     *
     * @throws Exception If `stored_procedure_params()` was not called first.
     *
     * @api
     *
     * @since 1.0.0
     */
    public function stored_procedure_values(array $values = []): self
    {
        if (! $this->is_sp_params_initialized) {
            $this->logger()->error('stored_procedure_values() called before stored_procedure_params()');
            throw new Exception('You must call stored_procedure_params() before stored_procedure_values().');
        }

        if (empty($this->params)) {
            $this->logger()->error('Values were set but no parameters exist');
            throw new Exception('Cannot call stored_procedure_values() if there are no parameters.');
        }

        $this->values = $values ?? [];
        $this->is_sp_values_initialized = true;

        $this->logger()->debug('Stored procedure values set', ['values' => $values]);

        return $this;
    }

    /**
     * Declare the output parameters. [Optional]
     *
     * This method tells the library which parameters should be treated as output variables.
     * You may pass either a simple array of names (defaulting to BIT for SQL Server, INT for MySQL),
     * or an associative array mapping parameter names to SQL types.
     *
     * @param  array  $output_params  OUTPUT/OUT parameter definitions with SQL types.
     * @return self Provides method chaining.
     *
     * @example
     * // Simple array (defaults to BIT type for SQL Server, INT for MySQL)
     * ->stored_procedure_output_params(['@result', '@status'])
     *
     * // Associative array with specific types
     * ->stored_procedure_output_params([
     *     '@result' => 'INT',
     *     '@message' => 'VARCHAR(255)',
     *     '@success' => 'BIT',        // SQL Server
     *     '@success' => 'TINYINT',    // MySQL equivalent
     *     '@created_date' => 'DATETIME'
     * ])
     *
     * @api
     *
     * @since 1.0.0
     */
    public function stored_procedure_output_params(array $output_params = []): self
    {
        // Normalize: if array is not associative, default type = BIT
        $normalized = [];
        foreach ($output_params as $key => $value) {
            if (is_int($key)) {
                // Just a param name, default to BIT
                $normalized[$value] = 'BIT';
            } else {
                // Explicit type provided
                $normalized[$key] = strtoupper($value);
            }
        }

        $this->output_params = $normalized;

        $this->logger()->debug('Output parameters set', ['output_params' => $this->output_params]);

        return $this;
    }

    /**
     * Enable database transaction wrapping during stored procedure execution. [Optional]
     *
     * **Use this only if your stored procedure does NOT handle its own transactions.**
     *
     * Laravel will begin a transaction before executing the procedure and commit it after execution.
     * If the procedure throws an error, the transaction will be rolled back automatically.
     *
     * #### Recommended Use:
     * - When your stored procedure performs multiple DML operations (INSERT, UPDATE, DELETE) **but does not manage transactions internally.**
     * - When you want Laravel to handle rollback automatically on exceptions.
     *
     * #### Avoid When:
     * - The stored procedure already includes `BEGIN TRANSACTION`, `COMMIT`, or `ROLLBACK`.
     * - You're calling nested stored procedures that manage their own transactions.
     *
     * @param  bool  $value  Whether to wrap the execution in a Laravel-managed transaction. Default is true.
     * @return self Provides method chaining.
     *
     * @api
     *
     * @since 1.0.0
     */
    public function with_transaction(bool $use_transaction = true): self
    {
        $this->use_transaction = $use_transaction;

        $this->logger()->debug('Transaction enabled', ['use_transaction' => $use_transaction]);

        return $this;
    }

    /**
     * Execute the stored procedure. [Required]
     *
     * This method **must be called last** in the method chain.
     *
     * @return self Provides method chaining.
     *
     * @throws Exception If `stored_procedure()` was not called first.
     *
     * @api
     *
     * @since 1.0.0
     */

    // Commented out because there is an optimized version of this method below
    // public function execute(): self
    // {
    //     if (!$this->is_sp_name_initialized) {
    //         $this->logger()->error("execute() called before stored_procedure()");
    //         throw new Exception('You must call stored_procedure() before execute().');
    //     }

    //     // If params are set, values must also be set.
    //     if ($this->is_sp_params_initialized && !$this->is_sp_values_initialized) {
    //         $this->logger()->error("stored_procedure_values() missing after stored_procedure_params()");
    //         throw new Exception('You must call stored_procedure_values() after stored_procedure_params().');
    //     }

    //     // $bindings = $this->command == 'CALL' ? ' (' . $this->params . ');' : ' ' . $this->params;

    //     // Construct the SQL query dynamically based on the database type
    //     $bindings = ($this->command === 'CALL')
    //         ? ($this->params ? " (" . $this->params . ");" : "();")
    //         // ? ($this->params ? " (" . $this->params . ");" : "")
    //         : ($this->params ? " " . $this->params : "");

    //     // Construct the final query
    //     $this->query = $this->query . $bindings;

    //     // Checks if a specific database connection is set, otherwise use the default connection
    //     $dbConnection = $this->connection === ''
    //         ? $this->db::connection()
    //         : $this->db::connection($this->connection);

    //     $this->logger()->info("Executing stored procedure", [
    //         'query' => $this->query,
    //         'values' => $this->values,
    //         'output_params' => $this->output_params,
    //         'use_transaction' => $this->use_transaction,
    //         'connection' => $this->connection ?: 'default',
    //     ]);

    //     try {
    //         if ($this->use_transaction) {
    //             $dbConnection->beginTransaction();
    //         }

    //         // This block is for output parameters only if they are set
    //         if (!empty($this->output_params)) {
    //             // Get the PDO instance from the database connection
    //             $pdo = $dbConnection->getPdo();

    //             // Perpare and execute the stored procedure call
    //             $stmt = $pdo->prepare($this->query);
    //             $stmt->execute($this->values);

    //             // Fetch the main result set, if any
    //             $this->result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //             $stmt->closeCursor();

    //             // Now, execute the second query to get the output parameter values
    //             $select_output_params_query = 'SELECT ' . implode(', ', $this->output_params);
    //             $output_stmt = $pdo->query($select_output_params_query);
    //             $this->output_results = $output_stmt->fetchAll(PDO::FETCH_ASSOC);
    //         } else {
    //             $this->result = empty($this->values)
    //                 ? $dbConnection->select($this->query)
    //                 : $dbConnection->select($this->query, $this->values);
    //         }

    //         if ($this->use_transaction) {
    //             $dbConnection->commit();
    //         }

    //         $this->logger()->info("Stored procedure executed successfully");
    //     } catch (Throwable $throwable) {
    //         if ($this->use_transaction) {
    //             $dbConnection->rollBack();
    //         }

    //         $this->logger()->error("Stored procedure execution failed", [
    //             'error' => $throwable->getMessage(),
    //             'exception' => get_class($throwable),
    //             'trace' => $throwable->getTraceAsString(),
    //             'query' => $this->query,
    //             'values' => $this->values,
    //         ]);

    //         throw $throwable;
    //     }

    //     $this->is_execute_called = true;

    //     return $this;
    // }

    // Optimized version of the execute method
    public function execute(): self
    {
        if (! $this->is_sp_name_initialized) {
            throw new Exception('You must call stored_procedure() before execute().');
        }

        if ($this->is_sp_params_initialized && ! $this->is_sp_values_initialized) {
            throw new Exception('You must call stored_procedure_values() after stored_procedure_params().');
        }

        // Build base call
        $bindings = ($this->command === 'CALL')
            ? ($this->params ? ' ('.$this->params.');' : '();')
            : ($this->params ? ' '.$this->params : '');

        $sp_call = $this->query.$bindings;

        // DB connection
        $db_connection = $this->connection === ''
            ? $this->db::connection()
            : $this->db::connection($this->connection);

        $this->logger()->info('Executing stored procedure', [
            'query' => $sp_call,
            'values' => $this->values,
            'output_params' => $this->output_params,
            'use_transaction' => $this->use_transaction,
            'connection' => $this->connection ?: 'default',
        ]);

        try {
            if ($this->use_transaction) {
                $db_connection->beginTransaction();
            }

            $pdo = $db_connection->getPdo();

            if (! empty($this->output_params) && $this->command === 'EXEC') {
                // OUTPUT param mode for SQL Server
                $declareStmts = [];
                $execCall = $sp_call;
                $selectStmts = [];

                foreach ($this->output_params as $param => $type) {
                    $cleanParam = trim(str_replace('OUTPUT', '', $param));
                    $declareStmts[] = "DECLARE $cleanParam $type;";
                    $selectStmts[] = "$cleanParam AS ".ltrim($cleanParam, '@');

                    // Add OUTPUT to the EXEC call *only if not already there*
                    $execCall = preg_replace(
                        '/('.preg_quote($cleanParam, '/').')(?!\s+OUTPUT\b)/i',
                        '$1 OUTPUT',
                        $execCall,
                        1 // replace once per param
                    );
                }

                $fullQuery = implode("\n", $declareStmts)."\n"
                    .$execCall."\n"
                    .'SELECT '.implode(', ', $selectStmts).';';

                $this->logger()->debug('Executing SQL Server OUTPUT param query', [
                    'query' => $fullQuery,
                    'values' => $this->values,
                    'driver' => $this->db_driver,
                ]);

                // Execute
                $stmt = $pdo->prepare($fullQuery);
                $stmt->execute($this->values);

                // Advance to the first result set that actually has columns
                while ($stmt->columnCount() === 0 && $stmt->nextRowset()) {
                    // keep advancing
                }

                // Capture OUTPUT scalars from the SELECT
                $this->output_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // No tabular dataset expected in this mode
                $this->result = [];
            } elseif (! empty($this->output_params) && $this->command === 'CALL') {
                // Initialize MySQL session variables
                foreach ($this->output_params as $param => $type) {
                    $cleanParam = trim(str_replace('OUT', '', $param));
                    $db_connection->statement("SET @$cleanParam = NULL");
                }
                
                // Execute the CALL statement (without OUT keywords)
                // Remove any OUT keywords that might be in the original call
                $cleanCall = $sp_call;
                foreach ($this->output_params as $param => $type) {
                    $cleanParam = trim(str_replace('OUT', '', $param));
                    // Remove OUT keyword if it exists in the call
                    $cleanCall = preg_replace(
                        '/('.preg_quote($cleanParam, '/').')\s+OUT\b/i',
                        '$1',
                        $cleanCall
                    );
                }
                
                $this->logger()->debug('Executing MySQL OUT param query', [
                    'call_query' => $cleanCall,
                    'values' => $this->values,
                    'driver' => $this->db_driver,
                ]);
                
                // Execute the stored procedure call
                $db_connection->select($cleanCall, $this->values);
                
                // Fetch OUTPUT variables separately
                $selectStmts = [];
                foreach ($this->output_params as $param => $type) {
                    $cleanParam = trim(str_replace('OUT', '', $param));
                    $selectStmts[] = "@$cleanParam AS ".ltrim($cleanParam, '@');
                }
                
                $selectQuery = 'SELECT '.implode(', ', $selectStmts);
                
                $this->logger()->debug('Fetching MySQL OUT parameters', [
                    'select_query' => $selectQuery,
                ]);
                
                $this->output_results = $db_connection->select($selectQuery);
                
                // No tabular dataset expected in this mode
                $this->result = [];

            } else {
                // Normal mode (no OUTPUT params) → return dataset
                $this->result = empty($this->values)
                    ? $db_connection->select($sp_call)
                    : $db_connection->select($sp_call, $this->values);
            }

            if ($this->use_transaction) {
                $db_connection->commit();
            }

            $this->logger()->info('Stored procedure executed successfully');
        } catch (Throwable $throwable) {
            if ($this->use_transaction) {
                $db_connection->rollBack();
            }

            $this->logger()->error('Stored procedure execution failed', [
                'error' => $throwable->getMessage(),
                'exception' => get_class($throwable),
                'trace' => $throwable->getTraceAsString(),
                'query' => $sp_call,
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
     * Returns different types based on whether OUTPUT parameters are used:
     * - Without OUTPUT params: Laravel Collection
     * - With OUTPUT params: Object with 'result' (Collection) and 'output' (scalar/object) properties
     *
     * @return Collection|object The stored procedure result.
     *                           - Collection: When no OUTPUT parameters are used
     *                           - object: When OUTPUT parameters are used, contains 'result' and 'output' properties
     *
     * @throws Exception If `execute()` was not called first.
     *
     * @example
     * // Without OUTPUT parameters
     * $users = $result->stored_procedure_result(); // Collection
     *
     * // With OUTPUT parameters
     * $response = $result->stored_procedure_result();
     * $data = $response->result;    // Collection
     * $count = $response->output;   // Scalar or object
     *
     * @api
     *
     * @since 1.0.0
     */

    // Commented out because it was returning a collection or an array
    // public function stored_procedure_result(): Collection|array
    // {
    //     if (!$this->is_execute_called) {
    //         $this->logger()->error("Attempted to retrieve stored procedure result before execution");
    //         throw new Exception('You must call execute() before stored_procedure_result().');
    //     }

    //     $this->logger()->debug("Returning stored procedure result", [
    //         'records_found' => count($this->result ?? [])
    //     ]);

    //     $result = $this->result;

    //     $this->autoReset();

    //     // Return results as a Laravel Collection or an empty Collection if no records were found
    //     // return collect($this->result)->count() > 0
    //     //     ? Collection::make($this->result)
    //     //     : Collection::make([]);
    //     return collect($result)->count() > 0 ? Collection::make($result) : Collection::make([]);
    // }

    public function stored_procedure_result(): mixed
    {
        if (! $this->is_execute_called) {
            $this->logger()->error('Attempted to retrieve stored procedure result before execution');
            throw new Exception('You must call execute() before stored_procedure_result().');
        }

        $this->logger()->debug('Returning stored procedure result (smart mode)', [
            'records_found' => count($this->result ?? []),
            'output_results' => $this->output_results,
        ]);

        $result = $this->result ?? [];
        $outputs = $this->output_results;

        $this->autoReset();

        // If no outputs captured, return just the dataset as a Collection
        if (empty($outputs)) {
            return collect($result)->count() > 0 ? Collection::make($result) : Collection::make([]);
        }

        // If outputs exist, return object { result, output }
        return (object) [
            'result' => collect($result)->count() > 0 ? Collection::make($result) : Collection::make([]),
            'output' => $this->normalizeOutput($outputs),
        ];
    }

    /**
     * Normalize the output parameters.
     *
     * Converts the raw output parameter array into a more usable format:
     * - Single parameter: Returns scalar value
     * - Multiple parameters: Returns object with property access
     *
     * @param  array  $outputs  The output parameters.
     * @return mixed Scalar (int/string/etc.), object (stdClass), or null
     *
     * @since 1.0.0
     */
    private function normalizeOutput(array $outputs): mixed
    {
        if (empty($outputs)) {
            return null;
        }

        $row = $outputs[0] ?? [];

        // Single OUTPUT param → scalar
        if (count($row) === 1) {
            return reset($row);
        }

        // Multiple OUTPUT params → object with property-style access
        return (object) $row;
    }

    /**
     * Retrieve OUTPUT parameter results from the stored procedure.
     *
     * - If there is only one OUTPUT param → return scalar directly.
     * - If there are multiple OUTPUT params → return an object (stdClass),
     *   so you can access results as $outputs->out1, $outputs->out2, etc.
     *
     * @return mixed Scalar (int/string/etc.), object (stdClass), or null
     *
     * @throws Exception If execute() was not called first.
     *
     * @example
     * // Single OUTPUT parameter
     * $count = $result->stored_procedure_output_results(); // Scalar value
     *
     * // Multiple OUTPUT parameters
     * $outputs = $result->stored_procedure_output_results();
     * $success = $outputs->success;    // Property access
     * $message = $outputs->message;    // Property access
     *
     * @api
     *
     * @since 1.0.0
     */
    public function stored_procedure_output_results(): mixed
    {
        if (! $this->is_execute_called) {
            $this->logger()->error('Attempted to retrieve output params before execution');
            throw new Exception('You must call execute() before get_output_params().');
        }

        $this->logger()->debug('Returning output parameters', [
            'output_results' => $this->output_results,
        ]);

        if (empty($this->output_results)) {
            return null;
        }

        // First row of output params
        $row = $this->output_results[0] ?? [];

        // Single OUTPUT param → return scalar
        if (count($row) === 1) {
            return reset($row);
        }

        // Multiple OUTPUT params → return as object for property-style access
        return (object) $row;
    }

    /**
     * Automatically reset internal state after execution.
     *
     * Prevents results from being overwritten by subsequent calls while preserving
     * the current execution results for later access.
     *
     * @since 1.0.0
     */
    private function autoReset(): void
    {
        $preserved_result = $this->result;
        $preserved_output_results = $this->output_results;

        $this->query = null;
        $this->params = null;
        $this->values = null;
        $this->connection = null;
        $this->use_transaction = false;
        $this->output_params = [];

        $this->is_sp_name_initialized = false;
        $this->is_sp_params_initialized = false;
        $this->is_sp_values_initialized = false;
        $this->is_execute_called = false;

        // Preserve result for later use
        $this->result = $preserved_result;
        $this->output_results = $preserved_output_results;
    }

    /**
     * Manually reset the instance state.
     *
     * This method clears all internal state and results, allowing the instance
     * to be reused for a new stored procedure call.
     *
     * @return self Provides method chaining.
     *
     * @example
     * $sp = new StoredProcedure();
     * $sp->stored_procedure('proc1')->execute()->stored_procedure_result();
     * $sp->reset(); // Clear state for reuse
     * $sp->stored_procedure('proc2')->execute()->stored_procedure_result();
     *
     * @api
     *
     * @since 1.0.0
     */
    public function reset(): self
    {
        $this->autoReset();
        $this->result = null;
        $this->output_results = [];

        return $this;
    }
}
