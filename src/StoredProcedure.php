<?php

namespace MagsLabs\LaravelStoredProc;

use Illuminate\Http\Client\Request;
use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * @method static StoredProcedure stored_procedure(string $procedure = '')
 * @method static StoredProcedure stored_procedure_connection(string $connection = '')
 * @method static StoredProcedure stored_procedure_params(array | Request| FormRequest $params = [])
 * @method static StoredProcedure stored_procedure_values(array $values = [])
 * @method static StoredProcedure execute()
 * @method static StoredProcedure stored_procedure_result()
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

    public function __construct()
    {
        $this->db = DB::class;
        $this->db_driver = $this->db::getConfig("driver");

        switch ($this->db_driver) {
            case 'mysql':
                $this->command = 'CALL';
                break;
            case 'sqlsrv':
                $this->command = 'EXEC';
                break;
            default:
                $this->command = 'CALL';
                break;
        }
    }

    /**
     * [Required]
     * The stored_procedure method sets the stored procedure $procedure to be executed.
     * 
     * Call the stored_procedure method as the first method to set the stored procedure to be executed.
     * 
     * @param string $procedure *$procedure should be a string. Default is an empty string.
     * @return static *returns the stored procedure object
     */
    public function stored_procedure(
        string $procedure = '')
    {
        $this->query = $this->command . ' ' . $procedure;

        return $this;
    }

    /**
     * [Optional]
     * The stored_procedure_connection method sets the connection for the stored procedure.
     * The connection parameter is used to specify which database connection to use when executing the stored procedure.
     * 
     * Call the stored_procedure_connection method after calling the stored_procedure method to set the connection for the stored procedure.
     * 
     * @param string $connection *$connection should be a string. Default is the default database connection you have set in your .env [DB_CONNECTION] file.
     * Example: 'mysql', 'sqlsrv', 'pgsql', 'sqlite', 'sqlsrv', 'your_connection_name'.
     * 
     * @return static *returns the stored procedure object
     */
    public function stored_procedure_connection(string $connection = '')
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * [Optional] -> [Required if your stored procedure has parameters]
     * 
     * @depends stored_procedure
     * 
     * The stored_procedure_params method sets the parameters for the stored procedure.
     * 
     * Call the stored_procedure_params method after calling the stored_procedure method to set the parameters for the stored procedure.
     * A caveat is that the stored_procedure_params method can only be called if your stored procedure has parameters.
     * 
     * @param array | Request| FormRequest $params *$params should be an instance of array, Request, or FormRequest. Default is an empty array.
     * @return static *returns the stored procedure object
     */
    public function stored_procedure_params(array | Request| FormRequest $params = [])   
    {
        if($params instanceof Request || $params instanceof FormRequest)
        {
            // unset the _token from the request
            unset($params['_token']);

            // extract the key names from the request
            $params_keys = array_keys($params->toArray());

            // declare an array to store the key names
            $params_keys_array = [];

            // populate the params keys array with the key names
            foreach ($params_keys as $key) {
                $i = ':' . $key;
                $params_keys_array[] = $i;
            }

            // implode the array to stringify the keys
            $this->params = implode(', ', $params_keys_array);
        }
        else
        {
            $this->params = implode(', ', $params) ?? '';
        }

        return $this;
    }

    /**
     * [Optional] -> [Required if your stored procedure has parameters]
     * 
     * @depends stored_procedure_params
     * 
     * The stored_procedure_values method sets the values for the stored procedure.
     * 
     * Call the stored_procedure_values method after calling the stored_procedure_params method to set the values for the stored procedure.
     * 
     * @param array $values *$values should be an instance of array. Default is an empty array.
     * Example: [$value1, $value2, $value3, $value_N...]
     * 
     * @return static *returns the stored procedure object
     */
    public function stored_procedure_values(array $values = [])
    {
        $this->values = $values ?? [];
        return $this;
    }

    /**
     * [Required]
     * 
     * @depends stored_procedure
     * 
     * The execute method executes the stored procedure.
     * 
     * Call the execute method as the last method to execute the stored procedure.
     * 
     * @return static *returns the stored procedure object
     */
    public function execute()
    {
        $bindings = $this->command == 'CALL' ? ' (' . $this->params . ');' : ' ' . $this->params;
        $this->query = $this->query . $bindings;

        if ($this->connection == ''){
            if($this->values == []){
                $this->result = $this->db::select( $this->query);
            } else {
                $this->result = $this->db::select( $this->query, $this->values);
            }
        } else {
            if($this->values == []){
                $this->result = $this->db::connection($this->connection)->select( $this->query);
            } else {
                $this->result = $this->db::connection($this->connection)->select( $this->query, $this->values);
            }
        }
        return $this;
    }

    /**
     * [Required]
     * 
     * @depends stored_procedure
     * @depends execute
     * 
     * The stored_procedure_result method returns the result of the stored procedure as a collection or an array.
     * 
     * Call the stored_procedure_result method after calling the execute method to retrieve the result of the stored procedure.
     * 
     * @return collection|array *returns a collection of results or an array of results
     */
    public function stored_procedure_result()
    {
        $record_count = collect($this->result)->count();

        if ($record_count > 0){
            return Collection::make($this->result);
        } else {
            return Collection::make([]);
        }
    }

}
