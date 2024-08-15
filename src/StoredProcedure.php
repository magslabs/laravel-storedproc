<?php

namespace MagsLabs\LaravelStoredProc;

use Illuminate\Http\Client\Request;

use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Summary of StoredProcedure
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
     * Summary of stored_procedure - Sets the stored procedure to be executed.
     * @param string $procedure
     * @return static
     */
    public function stored_procedure(
        string $procedure = '')
    {
        $this->query = $this->command . ' ' . $procedure;

        return $this;
    }

    /**
     * Summary of stored_procedure_connection - Sets the connection for the stored procedure.
     * @param string $connection
     * @return static
     */
    public function stored_procedure_connection(string $connection = '')
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Summary of stored_procedure_params - Sets the parameters for the stored procedure.
     * @param Request|FormRequest|array $params - $params should be an instance of Request, FormRequest or array
     * Request and FormRequest are used to get the parameters from the request from the client.
     * array is used to get the custom parameters from the array.
     * @return static
     */
    public function stored_procedure_params($params = [])
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
     * Summary of stored_procedure_values - Sets the values for the stored procedure.
     * @param array $values - $values should be an instance of array
     * @return static
     */
    public function stored_procedure_values(array $values = [])
    {
        $this->values = $values ?? [];
        return $this;
    }

    /**
     * Summary of execute - Executes the stored procedure.
     * @return static
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
     * Summary of stored_procedure_result - returns the result of the stored procedure.
     * @param bool $return_type
     * @return mixed collection or array - depends on the $return_type parameter value. Default is array.
     */
    public function stored_procedure_result()
    {
        $record_count = collect($this->result)->count();

        if ($record_count > 0 && $this->result instanceof Collection) {
            return Collection::make($this->result);
        } else if ($record_count > 0 && is_array($this->result)) {
            return $this->result;
        } else {
            return [];
        }
    }

}
